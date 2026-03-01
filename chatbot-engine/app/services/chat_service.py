from langchain_core.chat_history import BaseChatMessageHistory, InMemoryChatMessageHistory
from langchain_core.messages import HumanMessage, AIMessage
from langchain_core.prompts import ChatPromptTemplate, MessagesPlaceholder
from langchain_core.runnables.history import RunnableWithMessageHistory
from langchain_openai import ChatOpenAI

from app.core.config import settings

SYSTEM_PROMPT = "너는 10년 경력의 친절한 여행 플래너야"

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
            model=settings.openai_model,          # gpt-4o-mini
            temperature=settings.openai_temperature,
            max_tokens=settings.openai_max_tokens,
        )

        prompt = ChatPromptTemplate.from_messages([
            ("system", SYSTEM_PROMPT),
            MessagesPlaceholder(variable_name="history"),
            ("human", "{message}"),
        ])

        # RunnableWithMessageHistory: 호출마다 자동으로 세션 히스토리 로드/저장
        self._chain = RunnableWithMessageHistory(
            prompt | llm,
            _get_session_history,
            input_messages_key="message",
            history_messages_key="history",
        )

    def chat(self, message: str, session_id: str) -> str:
        """
        세션 메모리를 유지하며 AI 응답을 반환합니다.

        Args:
            message:    사용자 입력 메시지
            session_id: 대화 세션 식별자 (클라이언트가 유지)

        Returns:
            AI 응답 문자열
        """
        response = self._chain.invoke(
            {"message": message},
            config={"configurable": {"session_id": session_id}},
        )
        return response.content

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
