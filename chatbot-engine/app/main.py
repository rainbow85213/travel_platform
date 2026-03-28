from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.routes import chat
from app.core.config import settings


# ---------------------------------------------------------------------------
# Lifespan
# ---------------------------------------------------------------------------

@asynccontextmanager
async def lifespan(app: FastAPI):
    print(f"[{settings.app_name}] 서버 시작 (model: {settings.openai_model})")
    yield
    print(f"[{settings.app_name}] 서버 종료")


# ---------------------------------------------------------------------------
# App
# ---------------------------------------------------------------------------

app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
    description="여행 플랫폼 챗봇 엔진. LangChain + GPT-4o-mini 기반 여행 플래너 AI.",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan,
)


# ---------------------------------------------------------------------------
# CORS
# Laravel 백엔드(Sail: localhost:80)와 React Native 앱에서의 호출을 허용합니다.
# 허용 도메인은 ALLOWED_ORIGINS 환경변수(JSON 배열)로 주입하세요.
# ---------------------------------------------------------------------------

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "DELETE", "OPTIONS"],
    allow_headers=["Authorization", "Content-Type", "Accept"],
)


# ---------------------------------------------------------------------------
# Routers
# ---------------------------------------------------------------------------

app.include_router(chat.router)  # /chat, /chat/{session_id}/history, ...


# ---------------------------------------------------------------------------
# Root
# ---------------------------------------------------------------------------

@app.get("/", tags=["root"], summary="서비스 정보")
async def root() -> dict:
    return {
        "name": settings.app_name,
        "version": settings.app_version,
        "model": settings.openai_model,
        "docs": "/docs",
        "endpoints": {
            "chat":    "POST /chat",
            "history": "GET  /chat/{session_id}/history",
            "clear":   "DELETE /chat/{session_id}",
            "health":  "GET  /chat/health",
        },
    }
