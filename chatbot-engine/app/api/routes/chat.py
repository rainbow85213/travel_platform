import uuid

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from app.services.chat_service import ChatService

router = APIRouter(prefix="/chat", tags=["chat"])

# 서비스는 앱 수명 동안 단일 인스턴스 유지 (세션 스토어 공유)
_service = ChatService()


# ---------------------------------------------------------------------------
# Schemas
# ---------------------------------------------------------------------------

class ChatRequest(BaseModel):
    message: str = Field(
        ...,
        min_length=1,
        max_length=2000,
        examples=["제주도 3박 4일 일정 추천해 줘"],
    )
    session_id: str = Field(
        default_factory=lambda: str(uuid.uuid4()),
        description="대화 세션 ID. 최초 요청 시 생략하면 자동 생성됩니다.",
        examples=["user-123-session-abc"],
    )


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
    - **session_id**: 세션 식별자. 동일 session_id로 반복 요청하면 대화 맥락이 유지됩니다.

    최초 요청 시 `session_id`를 생략하면 UUID가 자동 발급되어 응답에 포함됩니다.
    이후 요청에서는 발급된 `session_id`를 반드시 포함하세요.
    """
    try:
        reply = _service.chat(message=body.message, session_id=body.session_id)
        return ChatResponse(data={"reply": reply, "session_id": body.session_id})
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"AI 서비스 오류: {e!s}")


@router.get("/{session_id}/history", summary="대화 이력 조회")
async def get_history(session_id: str) -> ChatResponse:
    """지정한 세션의 전체 대화 이력을 반환합니다."""
    history = _service.get_history(session_id)
    return ChatResponse(data={"session_id": session_id, "history": history})


@router.delete("/{session_id}", summary="세션 대화 이력 삭제")
async def clear_session(session_id: str) -> ChatResponse:
    """지정한 세션의 대화 이력을 모두 삭제합니다."""
    _service.clear_session(session_id)
    return ChatResponse(message="세션이 삭제되었습니다.", data={"session_id": session_id})


@router.get("/health", summary="헬스 체크")
async def health() -> dict:
    return {"status": "ok"}
