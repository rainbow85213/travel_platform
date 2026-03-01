from langchain_openai import ChatOpenAI
from langchain_core.messages import HumanMessage, SystemMessage, AIMessage
from langchain_core.prompts import ChatPromptTemplate, MessagesPlaceholder

from app.core.config import settings


SYSTEM_PROMPT = """당신은 여행 플랫폼의 친절한 AI 여행 도우미입니다.
사용자의 여행 계획, 여행지 추천, 일정 구성 등을 도와줍니다.
항상 한국어로 답변하고, 구체적이고 실용적인 정보를 제공하세요."""


class ChatService:
    def __init__(self) -> None:
        self._llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model,
            temperature=settings.openai_temperature,
            max_tokens=settings.openai_max_tokens,
        )

        self._prompt = ChatPromptTemplate.from_messages([
            ("system", SYSTEM_PROMPT),
            MessagesPlaceholder(variable_name="history"),
            ("human", "{message}"),
        ])

        self._chain = self._prompt | self._llm

    def chat(self, message: str, history: list[dict] | None = None) -> str:
        """
        단일 메시지 응답.

        Args:
            message:  사용자 메시지
            history:  이전 대화 목록 [{"role": "user"|"assistant", "content": "..."}]

        Returns:
            AI 응답 문자열
        """
        lc_history = self._build_history(history or [])
        response = self._chain.invoke({"message": message, "history": lc_history})
        return response.content

    def _build_history(self, history: list[dict]) -> list[HumanMessage | AIMessage]:
        messages: list[HumanMessage | AIMessage] = []
        for item in history:
            if item.get("role") == "user":
                messages.append(HumanMessage(content=item["content"]))
            elif item.get("role") == "assistant":
                messages.append(AIMessage(content=item["content"]))
        return messages
