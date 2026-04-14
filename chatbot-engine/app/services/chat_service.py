from langchain_core.messages import AIMessage, HumanMessage, SystemMessage
from langchain_core.runnables import RunnableConfig
from langchain_openai import ChatOpenAI
from langgraph.checkpoint.memory import MemorySaver
from langgraph.prebuilt import create_react_agent

from app.core.config import settings
from app.schemas.itinerary import ChatOutput, ItineraryItem
from app.tools.travel_tools import (
    finalize_itinerary,
    get_tourist_spot_detail,
    search_accommodations,
    search_nearby_places,
    search_places,
    search_tourist_spots,
)


def _build_system_prompt(lat: float | None, lng: float | None) -> str:
    base = (
        "너는 10년 경력의 친절한 여행 플래너야. 항상 한국어로 답변해.\n\n"
        "## 도구 사용 규칙\n"
        "- 특정 지역의 관광지·명소를 찾을 때: search_tourist_spots 사용 (keyword 또는 city 필수)\n"
        "- 관광지 상세 정보가 필요할 때: get_tourist_spot_detail 사용\n"
        "- 숙박 시설을 찾을 때: search_accommodations 사용\n"
        "- Laravel에 등록된 장소를 찾을 때: search_places 사용\n"
        "- '근처', '주변', '가까운', '여기서' 등 위치 기반 표현이 나오면 "
        "반드시 search_nearby_places 도구를 사용해. 절대 일반 추천으로 대체하지 마.\n"
        "- 반드시 도구로 실제 데이터를 조회한 후 답변해. 임의로 장소를 지어내지 마.\n\n"
        "## 일정 확정 규칙 (중요)\n"
        "사용자가 '확정', '이걸로 해줘', '좋아', '그대로 가자' 등 일정에 동의하면 "
        "반드시 finalize_itinerary 도구를 호출해야 해.\n"
        "- 텍스트로 JSON을 출력하는 것은 절대 금지\n"
        "- finalize_itinerary 호출 시 모든 장소의 일차·시간·장소명·위도·경도·설명을 빠짐없이 포함\n"
        "- 위도·경도는 실제 좌표값을 사용 (모르면 합리적인 근사값 사용)\n"
        "- 일정이 아직 논의 중이거나 사용자가 동의하지 않은 상태에서는 호출하지 마\n"
    )
    if lat is not None and lng is not None:
        base += (
            f"\n## 사용자 현재 위치\n"
            f"위도: {lat:.6f}, 경도: {lng:.6f}\n"
            f"'근처', '주변', '가까운' 등의 표현이 나오면 이 좌표를 기준으로 "
            f"search_nearby_places 도구를 즉시 호출해. 위치를 모른다는 말은 절대 하지 마.\n"
        )
    return base


def _location_aware_prompt(state: dict, config: RunnableConfig) -> list:
    """
    create_react_agent의 prompt callable.
    configurable에서 위도/경도를 읽어 SystemMessage를 동적으로 생성한다.
    히스토리에 SystemMessage가 누적되지 않도록 매 invoke 시 prepend 방식으로 주입.
    """
    configurable = config.get("configurable", {}) if config else {}
    lat = configurable.get("latitude")
    lng = configurable.get("longitude")
    return [SystemMessage(content=_build_system_prompt(lat, lng))] + list(state["messages"])


class ChatService:
    def __init__(self) -> None:
        llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model,
            temperature=settings.openai_temperature,
            max_tokens=settings.openai_max_tokens,
        )

        tools = [search_places, search_tourist_spots, get_tourist_spot_detail,
                 search_accommodations, search_nearby_places, finalize_itinerary]

        # LangGraph checkpointer — 인메모리 세션 관리
        # Upstash Redis는 RediSearch(FT.*)를 미지원하므로 MemorySaver 사용
        # 채팅 히스토리는 Laravel chat_messages 테이블에 영속 저장됨
        self._checkpointer = MemorySaver()

        # prompt를 2-argument callable로 전달: (state, config) → list[BaseMessage]
        # LangGraph가 RunnableLambda로 래핑해 매 invoke 시 config.configurable에서
        # latitude/longitude를 읽어 SystemMessage를 동적 생성한다.
        # 이 방식은 thread 히스토리에 SystemMessage가 누적되지 않는다.
        self._agent = create_react_agent(
            llm,
            tools,
            prompt=_location_aware_prompt,
            checkpointer=self._checkpointer,
        )

    def chat(
        self,
        message: str,
        session_id: str,
        latitude: float | None = None,
        longitude: float | None = None,
    ) -> ChatOutput:
        """
        세션 메모리를 유지하며 AI(Tool Calling Agent) 응답을 반환합니다.

        Args:
            message:    사용자 입력 메시지
            session_id: 대화 세션 식별자
            latitude:   사용자 현재 위도 (옵션)
            longitude:  사용자 현재 경도 (옵션)

        Returns:
            ChatOutput (type, message, itinerary)
        """
        result = self._agent.invoke(
            {"messages": [HumanMessage(content=message)]},
            config={
                "configurable": {
                    "thread_id": session_id,
                    "latitude": latitude,
                    "longitude": longitude,
                }
            },
        )

        messages = result.get("messages", [])

        # 마지막 AI 텍스트 응답 추출
        ai_text = ""
        for msg in reversed(messages):
            if isinstance(msg, AIMessage) and msg.content:
                ai_text = msg.content if isinstance(msg.content, str) else str(msg.content)
                break

        # finalize_itinerary 호출 여부 및 args 추출
        itinerary: list[ItineraryItem] | None = None
        for msg in messages:
            if not isinstance(msg, AIMessage):
                continue
            for tc in (msg.tool_calls or []):
                if tc.get("name") == "finalize_itinerary":
                    raw_items = tc.get("args", {}).get("items", [])
                    try:
                        itinerary = [
                            ItineraryItem(**i) if isinstance(i, dict) else i
                            for i in raw_items
                        ]
                    except Exception:
                        itinerary = None
                    break
            if itinerary is not None:
                break

        return ChatOutput(
            type="itinerary" if itinerary else "message",
            message=ai_text,
            itinerary=itinerary,
        )

    def get_history(self, session_id: str) -> list[dict]:
        """세션의 대화 이력을 반환합니다."""
        try:
            state = self._agent.get_state(
                config={"configurable": {"thread_id": session_id}}
            )
            result = []
            for msg in state.values.get("messages", []):
                if isinstance(msg, HumanMessage):
                    result.append({"role": "user", "content": msg.content})
                elif isinstance(msg, AIMessage) and msg.content:
                    result.append({"role": "assistant", "content": msg.content})
            return result
        except Exception:
            return []

    def clear_session(self, session_id: str) -> None:
        """세션 대화 이력을 삭제합니다."""
        try:
            self._checkpointer.delete_thread(session_id)
        except (AttributeError, Exception):
            pass
