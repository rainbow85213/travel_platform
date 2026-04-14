# CLAUDE.md — chatbot-engine

## 1. 개요

TravelPlatform의 Python FastAPI 챗봇 엔진 서버.
LangGraph + GPT-4o-mini 기반 여행 플래너 AI.

상위 프로젝트: `TravelPlatform/` (Laravel 백엔드)

---

## 2. 배포 주의사항 (중요)

### fly.io 배포 시 반드시 chatbot-engine 디렉토리에서 실행

```bash
# ✅ 올바른 방법
cd chatbot-engine
fly deploy

# 또는
fly deploy --config chatbot-engine/fly.toml
```

```bash
# ❌ 잘못된 방법 — TravelPlatform 루트에서 실행
fly deploy --app travel-chatbot-engine
```

**이유:** TravelPlatform 루트에도 `fly.toml`이 있어서, 루트에서 실행하면
Laravel(PHP) Dockerfile이 `travel-chatbot-engine` 앱에 배포된다.
증상: 머신이 `php artisan migrate` + PostgreSQL 연결 오류로 재시작 반복.
이미지 크기가 ~94MB(정상) 대신 ~214MB(Laravel)로 나오면 잘못된 배포.

### fly.io 앱 정보

| 항목 | 값 |
|------|----|
| 앱 이름 | `travel-chatbot-engine` |
| 리전 | `nrt` (도쿄) |
| URL | `https://travel-chatbot-engine.fly.dev` |
| 헬스체크 | `GET /chat/health` |
| 정상 이미지 크기 | ~94MB |

---

## 3. 로컬 개발

### Python 실행 시 venv 직접 경로 사용

```bash
# ✅ 올바른 방법
./venv/bin/python3 -c "..."
./venv/bin/uvicorn app.main:app --reload --port 8001

# ❌ 잘못된 방법
source venv/bin/activate  # 심볼릭 링크 문제 발생 가능
```

### venv 재생성 (심볼릭 링크가 깨진 경우)

```bash
python3 -m venv venv --clear
./venv/bin/pip install -r requirements.txt
```

### 서버 실행

```bash
cd chatbot-engine
./venv/bin/uvicorn app.main:app --reload --port 8001
```

### 인증 토큰

로컬 테스트 시 `.env`의 `LARAVEL_SERVICE_TOKEN` 값을 사용:
```
Authorization: Bearer dev-token-2026
```

> TravelPlatform `.env`의 `LARAVEL_SERVICE_TOKEN`과 반드시 동일해야 함.

---

## 4. Secrets (fly.io)

```bash
fly secrets list --app travel-chatbot-engine
```

| 키 | 설명 |
|----|------|
| `OPENAI_API_KEY` | OpenAI API 키 |
| `LARAVEL_SERVICE_TOKEN` | Laravel ↔ chatbot-engine 내부 인증 토큰 (양쪽 동일값) |
| `LARAVEL_API_URL` | `https://travel-platform.fly.dev/api` |
| `REDIS_URL` | `redis://...@fly-travel-chatbot-redis.upstash.io:6379` |

> **주의:** Upstash Redis는 RediSearch(`FT.*`) 미지원 → `RedisSaver` 사용 불가.
> 현재 `MemorySaver` 사용 중 (재시작 시 세션 컨텍스트 초기화됨).
> 채팅 히스토리는 Laravel `chat_messages` 테이블에 영속 저장되므로 실용적 손실 없음.

---

## 5. 주요 커맨드

```bash
# 의존성 설치
./venv/bin/pip install -r requirements.txt

# 로컬 서버 실행
./venv/bin/uvicorn app.main:app --reload --port 8001

# fly.io 배포 (반드시 chatbot-engine 디렉토리에서)
cd chatbot-engine && fly deploy

# fly.io 로그 확인
fly logs --app travel-chatbot-engine

# fly.io 앱 상태 확인
fly status --app travel-chatbot-engine
```
