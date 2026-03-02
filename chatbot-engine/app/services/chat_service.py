from langchain.agents import create_agent
from langchain_core.messages import AIMessage, HumanMessage
from langchain_openai import ChatOpenAI
from langgraph.checkpoint.memory import MemorySaver

from app.core.config import settings
from app.schemas.itinerary import ChatOutput, ItineraryItem
from app.tools.travel_tools import finalize_itinerary, get_tour_detail, search_places, search_tours

SYSTEM_PROMPT = (
    "너는 10년 경력의 친절한 여행 플래너야. 항상 한국어로 답변해.\n\n"
    "## 도구 사용 규칙\n"
    "- 여행지·상품 정보 요청 시 search_places, search_tours, get_tour_detail 도구로 실제 데이터를 조회해.\n\n"
    "## 일정 확정 규칙 (중요)\n"
    "사용자가 '확정', '이걸로 해줘', '좋아', '그대로 가자' 등 일정에 동의하면 "
    "반드시 finalize_itinerary 도구를 호출해야 해.\n"
    "- 텍스트로 JSON을 출력하는 것은 절대 금지\n"
    "- finalize_itinerary 호출 시 모든 장소의 일차·시간·장소명·위도·경도·설명을 빠짐없이 포함\n"
    "- 위도·경도는 실제 좌표값을 사용 (모르면 합리적인 근사값 사용)\n"
    "- 일정이 아직 논의 중이거나 사용자가 동의하지 않은 상태에서는 호출하지 마\n"
)


class ChatService:
    def __init__(self) -> None:
        llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model,
            temperature=settings.openai_temperature,
            max_tokens=settings.openai_max_tokens,
        )

        tools = [search_places, search_tours, get_tour_detail, finalize_itinerary]

        # LangGraph checkpointer로 세션별 대화 이력 관리
        self._checkpointer = MemorySaver()
        self._agent = create_agent(
            llm,
            tools,
            system_prompt=SYSTEM_PROMPT,
            checkpointer=self._checkpointer,
        )

    def chat(self, message: str, session_id: str) -> ChatOutput:
        """
        세션 메모리를 유지하며 AI(Tool Calling Agent) 응답을 반환합니다.

        Args:
            message:    사용자 입력 메시지
            session_id: 대화 세션 식별자

        Returns:
            ChatOutput (type, message, itinerary)
        """
        result = self._agent.invoke(
            {"messages": [HumanMessage(content=message)]},
            config={"configurable": {"thread_id": session_id}},
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
        self._checkpointer.delete_thread(session_id)
