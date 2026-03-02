from langchain.agents import AgentExecutor, create_tool_calling_agent
from langchain_core.chat_history import BaseChatMessageHistory, InMemoryChatMessageHistory
from langchain_core.messages import AIMessage, HumanMessage
from langchain_core.prompts import ChatPromptTemplate, MessagesPlaceholder
from langchain_core.runnables.history import RunnableWithMessageHistory
from langchain_openai import ChatOpenAI

from app.core.config import settings
from app.tools.travel_tools import get_tour_detail, search_places, search_tours

SYSTEM_PROMPT = (
    "너는 10년 경력의 친절한 여행 플래너야. "
    "사용자가 여행지나 여행 상품 정보를 요청하면 반드시 제공된 도구(tool)를 사용해 "
    "실제 데이터를 조회한 뒤 한국어로 친절하고 구체적으로 답변해 줘."
)

# ---------------------------------------------------------------------------
# 세션 스토어 (in-memory, 서버 재시작 시 초기화)
# 프로덕션에서는 Redis 등 영속 스토리지로 교체
# ---------------------------------------------------------------------------
_store: dict[str, InMemoryChatMessageHistory] = {}


def _get_session_history(session_id: str) -> BaseChatMessageHistory:
    if session_id not in _store:
        _store[session_id] = InMemoryChatMessageHistory()
    return _store[session_id]


class ChatService:
    def __init__(self) -> None:
        llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model,
            temperature=settings.openai_temperature,
            max_tokens=settings.openai_max_tokens,
        )

        tools = [search_places, search_tours, get_tour_detail]

        # agent_scratchpad: 도구 호출/결과 중간 단계를 담는 슬롯
        prompt = ChatPromptTemplate.from_messages([
            ("system", SYSTEM_PROMPT),
            MessagesPlaceholder(variable_name="history"),
            ("human", "{message}"),
            MessagesPlaceholder(variable_name="agent_scratchpad"),
        ])

        agent = create_tool_calling_agent(llm, tools, prompt)
        agent_executor = AgentExecutor(agent=agent, tools=tools, verbose=False)

        # RunnableWithMessageHistory: 세션별 대화 맥락 자동 관리
        self._chain = RunnableWithMessageHistory(
            agent_executor,
            _get_session_history,
            input_messages_key="message",
            history_messages_key="history",
        )

    def chat(self, message: str, session_id: str) -> str:
        """
        세션 메모리를 유지하며 AI(Tool Calling Agent) 응답을 반환합니다.

        Args:
            message:    사용자 입력 메시지
            session_id: 대화 세션 식별자

        Returns:
            AI 최종 응답 문자열
        """
        response = self._chain.invoke(
            {"message": message},
            config={"configurable": {"session_id": session_id}},
        )
        return response["output"]

    def get_history(self, session_id: str) -> list[dict]:
        """세션의 대화 이력을 반환합니다."""
        history = _get_session_history(session_id)
        result = []
        for msg in history.messages:
            if isinstance(msg, HumanMessage):
                result.append({"role": "user", "content": msg.content})
            elif isinstance(msg, AIMessage):
                result.append({"role": "assistant", "content": msg.content})
        return result

    def clear_session(self, session_id: str) -> None:
        """세션 대화 이력을 삭제합니다."""
        if session_id in _store:
            del _store[session_id]
