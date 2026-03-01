from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from app.services.chat_service import ChatService

router = APIRouter(prefix="/chat", tags=["chat"])


# ---------------------------------------------------------------------------
# Schemas
# ---------------------------------------------------------------------------

class HistoryItem(BaseModel):
    role: str = Field(..., examples=["user", "assistant"])
    content: str


class ChatRequest(BaseModel):
    message: str = Field(..., min_length=1, max_length=2000, examples=["서울 여행 일정 추천해 줘"])
    history: list[HistoryItem] = Field(default_factory=list)


class ChatResponse(BaseModel):
    success: bool = True
    message: str = "성공"
    data: dict


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post("", response_model=ChatResponse, summary="챗봇 메시지 전송")
async def send_message(body: ChatRequest) -> ChatResponse:
    """
    사용자 메시지를 받아 AI 응답을 반환합니다.

    - **message**: 사용자 입력 메시지 (필수)
    - **history**: 이전 대화 목록 (선택, 멀티턴 지원)
    """
    try:
        service = ChatService()
        history = [h.model_dump() for h in body.history]
        reply = service.chat(message=body.message, history=history)
        return ChatResponse(data={"reply": reply})
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"AI 서비스 오류: {e!s}")


@router.get("/health", summary="헬스 체크")
async def health() -> dict:
    return {"status": "ok"}
