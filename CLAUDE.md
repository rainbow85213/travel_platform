# CLAUDE.md — TravelPlatform

## 1. Ecosystem Context

이 프로젝트는 **Travel-Ecosystem-Hub**의 핵심 백엔드 서버입니다.

```
Travel-Ecosystem-Hub/
├── TravelPlatform/   ← 현재 프로젝트 (메인 백엔드 + 챗봇 엔진)
├── TourCast/         ← 투어 상품 데이터 공급자 (외부 API 서버)
└── PlangoApp/        ← 모바일 프론트엔드 (React Native, API 소비자)
```

| 관계 | 방향 | 설명 |
|------|------|------|
| TourCast → TravelPlatform | 데이터 소비 | `TourCastService`가 TourCast REST API를 호출해 투어 목록/상세 데이터를 가져옴 |
| TravelPlatform → PlangoApp | 토큰 제공 | 로그인/회원가입 시 Sanctum Bearer Token 발급, PlangoApp이 모든 인증 요청에 사용 |

---

## 2. Tech Stack

### Laravel 백엔드

| 항목 | 버전 / 값 |
|------|-----------|
| PHP | `^8.2` |
| Laravel Framework | `^12.0` |
| Laravel Sanctum | `^4.3` (API 토큰 인증) |
| openai-php/laravel | `^0.18.0` (OpenAI 연동) |
| DB | PostgreSQL (Laravel Sail 기본값) |
| 캐시/세션/큐 | Database 드라이버 (기본), Redis 선택 가능 |
| 코드 포맷터 | Laravel Pint |
| 테스트 | PHPUnit `^11.5` |
| 개발 도구 | Laravel Sail, Pail, Tinker |

### Chatbot Engine (Python)

| 패키지 | 버전 |
|--------|------|
| FastAPI | `>=0.115.0` |
| uvicorn | `>=0.32.0` (ASGI 서버) |
| LangChain | `>=0.3.0` |
| langchain-openai | `>=0.2.0` |
| openai | `>=1.54.0` |
| pydantic / pydantic-settings | `>=2.9.0 / >=2.6.0` |
| httpx | `>=0.27.0` |
| python-dotenv | `>=1.0.0` |

---

## 3. Architecture

### 디렉토리 구조

```
app/
├── Http/
│   └── Controllers/
│       └── Api/          # API 컨트롤러 (6개)
│           ├── AuthController.php
│           ├── ChatController.php
│           ├── ItineraryController.php
│           ├── ItineraryItemController.php
│           ├── PlaceController.php
│           └── ProfileController.php
├── Models/               # Eloquent 모델 (6개)
│   ├── User.php
│   ├── UserPreference.php
│   ├── Place.php
│   ├── Itinerary.php     # SoftDelete 적용
│   ├── ItineraryItem.php
│   └── ChatMessage.php
├── Services/             # 외부 연동 서비스
│   ├── TourCastService.php   # TourCast API 클라이언트 (Guzzle + 재시도 로직)
│   └── TourCastException.php
├── Traits/
│   └── ApiResponse.php   # 표준 JSON 응답 헬퍼 (모든 컨트롤러에서 사용)
└── Providers/
    └── AppServiceProvider.php

chatbot-engine/           # Python FastAPI 챗봇 서버 (별도 프로세스)
```

### 인증 방식 (Sanctum)

- 모든 API 요청은 `Authorization: Bearer {token}` 헤더 필요
- 토큰 만료 시간: `SANCTUM_TOKEN_EXPIRATION` (기본 43200분 = 30일)
- `auth:sanctum` 미들웨어로 보호된 라우트 그룹 사용
- 다기기 로그아웃 지원 (`logout` vs `logout-all`)

### OpenAI 연동 방식

- `openai-php/laravel` 패키지를 통해 `config/openai.php`에서 설정 로드
- 모델: `OPENAI_MODEL` (기본 `gpt-4o-mini`), 타임아웃: 30초
- `ChatController` → OpenAI API 호출 → `chat_messages` 테이블에 대화 기록 저장
- `schedule` JSON 컬럼에 AI가 생성한 일정 데이터를 구조화해 저장

### 표준 API 응답 형식

모든 API 응답은 `ApiResponse` Trait을 통해 아래 형식을 따릅니다:

```json
{
  "success": true | false,
  "message": "응답 메시지",
  "data": { ... } | null,
  "errors": { ... }   // 유효성 검사 실패 시만 포함
}
```

### TourCastService 재시도 정책

- 최대 3회 재시도 (`TOURCAST_RETRY`)
- 지수 백오프: 500ms → 1,000ms → 2,000ms
- 재시도 조건: `ConnectException`, HTTP 429/500/502/503/504

---

## 4. Key API Endpoints

베이스 URL: `/api`
인증 헤더: `Authorization: Bearer {token}`

### 인증 (공개)

| Method | Path | 설명 |
|--------|------|------|
| POST | `/api/auth/register` | 회원가입 |
| POST | `/api/auth/login` | 로그인 → Sanctum 토큰 반환 |

### 인증 (토큰 필요)

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/auth/me` | 내 정보 조회 |
| POST | `/api/auth/logout` | 현재 기기 로그아웃 |
| POST | `/api/auth/logout-all` | 전체 기기 로그아웃 |

### 프로필

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/profile` | 프로필 조회 |
| PUT | `/api/profile` | 프로필 수정 |
| PUT | `/api/profile/password` | 비밀번호 변경 |
| GET | `/api/profile/preferences` | 선호 설정 조회 |
| PUT | `/api/profile/preferences` | 선호 설정 수정 |

### 장소 (읽기 전용)

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/places` | 장소 목록 (검색/필터) |
| GET | `/api/places/{place}` | 장소 상세 |

### 여행 일정

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/itineraries` | 내 일정 목록 |
| POST | `/api/itineraries` | 일정 생성 |
| POST | `/api/itineraries/save` | 챗봇 생성 일정 저장 |
| GET | `/api/itineraries/{itinerary}` | 일정 상세 (items.place 포함) |
| PUT | `/api/itineraries/{itinerary}` | 일정 수정 |
| DELETE | `/api/itineraries/{itinerary}` | 일정 삭제 (SoftDelete) |
| GET | `/api/itineraries/{itinerary}/items` | 일정 아이템 목록 |
| POST | `/api/itineraries/{itinerary}/items` | 아이템 추가 |
| PUT | `/api/itineraries/{itinerary}/items/{item}` | 아이템 수정 |
| DELETE | `/api/itineraries/{itinerary}/items/{item}` | 아이템 삭제 |

### 챗봇

| Method | Path | 설명 |
|--------|------|------|
| POST | `/api/chat` | 메시지 전송 → AI 응답 반환 |
| GET | `/api/chat/history` | 대화 기록 조회 |
| DELETE | `/api/chat/history` | 대화 기록 전체 삭제 |

---

## 5. Environment Variables

`.env.example` 기반 필수 환경 변수 목록:

### 앱 기본

```env
APP_NAME=TravelPlatform
APP_ENV=local          # local | production
APP_KEY=               # php artisan key:generate 로 생성
APP_DEBUG=true
APP_URL=http://localhost
```

### 데이터베이스 (PostgreSQL)

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql          # Sail 환경에서는 서비스명 사용
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

### Redis

```env
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
FORWARD_REDIS_PORT=6380  # 호스트에서 접근할 포트
```

### Sanctum 인증

```env
SANCTUM_TOKEN_EXPIRATION=43200  # 토큰 만료 시간 (분, 기본 30일)
```

### OpenAI

```env
OPENAI_API_KEY=          # 필수 — OpenAI 대시보드에서 발급
OPENAI_MODEL=gpt-4o-mini # 사용할 GPT 모델
```

### TourCast 연동

```env
TOURCAST_BASE_URL=https://api.tourcast.io/v1  # TourCast API 기본 URL
TOURCAST_API_KEY=                              # 필수 — TourCast에서 발급
TOURCAST_TIMEOUT=10    # 요청 타임아웃 (초)
TOURCAST_RETRY=3       # 최대 재시도 횟수
```

---

## 6. Database

PostgreSQL 사용. 타임스탬프 컬럼은 타임존 포함(`timestampsTz`).

### 테이블 개요

| 테이블 | 설명 | 주요 컬럼 |
|--------|------|-----------|
| `users` | 사용자 계정 | `name`, `email` (unique), `password` |
| `user_preferences` | 사용자 설정 (1:1) | `language`(ko), `currency`(KRW), `timezone`(Asia/Seoul), `notification_enabled` |
| `personal_access_tokens` | Sanctum 토큰 | Laravel Sanctum 기본 스키마 |
| `places` | 여행 장소 | `name`, `city`, `country`, `latitude`/`longitude`(소수점 7자리), `category`, `rating`(0.00~5.00), **SoftDelete** |
| `itineraries` | 여행 일정 | `user_id`, `title`, `start_date`, `end_date`, `status`(draft/published/archived), **SoftDelete** |
| `itinerary_items` | 일정 아이템 (중첩) | `itinerary_id`, `place_id`, `day_number`, `sort_order`, `visited_at`, `duration_minutes`, `notes` |
| `chat_messages` | 챗봇 대화 기록 | `user_id`, `role`(user/assistant), `text`, `schedule`(JSON — AI 생성 일정 데이터) |
| `sessions` | 데이터베이스 세션 | Laravel 기본 |
| `cache` | 데이터베이스 캐시 | Laravel 기본 |
| `jobs` | 큐 작업 | Laravel 기본 |

### 인덱스 전략

- `places`: `(city, country)`, `category` 복합 인덱스
- `itineraries`: `(user_id, status)`, `(start_date, end_date)` 복합 인덱스
- `itinerary_items`: `(itinerary_id, day_number, sort_order)` 복합 인덱스
- `chat_messages`: `(user_id, created_at)` 복합 인덱스

### 외래 키 정책

- `itinerary_items.itinerary_id` → `cascadeOnDelete` (일정 삭제 시 아이템도 삭제)
- `itinerary_items.place_id` → `restrictOnDelete` (참조 중인 장소 삭제 불가)
- `user_preferences.user_id` → `cascadeOnDelete`

---

## 7. Cross-Repo Rules

### TourCast 데이터 소비 방식

`app/Services/TourCastService.php`가 TourCast API와의 통신을 전담합니다.

```php
// 사용 예시
$service = app(TourCastService::class);
$tours   = $service->getTours(['category' => 'adventure', 'page' => 1]);
$tour    = $service->getTour(42);
$results = $service->searchTours('제주도');
```

- 설정: `config/services.php`의 `tourcast` 키
- 인증: `Authorization: Bearer {TOURCAST_API_KEY}` 헤더 자동 첨부
- 실패 시 `TourCastException` throw — 컨트롤러에서 catch 후 `failure()` 응답 반환

### PlangoApp에 토큰 제공 방식

```json
// POST /api/auth/login 응답 예시
{
  "success": true,
  "message": "로그인 성공",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "홍길동", "email": "user@example.com" }
  }
}
```

- PlangoApp은 이 토큰을 로컬 스토리지에 저장하고 모든 요청 헤더에 포함
- 토큰 갱신 없음 — 만료 시 재로그인 필요 (만료: `SANCTUM_TOKEN_EXPIRATION`)
- 다기기 동시 로그인 허용, `logout-all`로 모든 토큰 무효화 가능

---

## 8. Commit Convention

모든 커밋 메시지는 반드시 아래 형식을 따릅니다:

```
[TravelPlatform] Type: 내용 요약
```

### Type 목록

| Type | 용도 |
|------|------|
| `Feat` | 새로운 기능 추가 |
| `Fix` | 버그 수정 |
| `Refactor` | 기능 변경 없는 코드 개선 |
| `Docs` | 문서 수정 |
| `Test` | 테스트 추가/수정 |
| `Chore` | 빌드, 설정, 의존성 변경 |
| `Style` | 코드 포맷, 세미콜론 등 로직 무관 변경 |

### 예시

```
[TravelPlatform] Feat: 챗봇 대화 기록 삭제 엔드포인트 추가
[TravelPlatform] Fix: TourCastService 재시도 횟수 설정값 미적용 버그 수정
[TravelPlatform] Refactor: ItineraryController SoftDelete 처리 로직 분리
```

---

## 9. Common Commands

### 초기 설정

```bash
# 전체 초기화 (설치 → .env 복사 → 키 생성 → 마이그레이션 → 프론트 빌드)
composer run setup
```

### 개발 서버 실행

```bash
# 병렬 실행: PHP 서버 + 큐 + 로그(Pail) + Vite
composer run dev

# 개별 실행
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0
```

### 테스트

```bash
# 전체 테스트 (config 캐시 초기화 후 실행)
composer test

# 특정 테스트만 실행
php artisan test --filter=ChatControllerTest

# PHPUnit 직접 실행
./vendor/bin/phpunit
```

### 코드 포맷

```bash
# Laravel Pint로 코드 스타일 자동 수정
./vendor/bin/pint

# 수정 없이 확인만
./vendor/bin/pint --test
```

### 데이터베이스

```bash
# 마이그레이션 실행
php artisan migrate

# 마이그레이션 롤백
php artisan migrate:rollback

# 리셋 후 재실행 (Seeder 포함)
php artisan migrate:fresh --seed

# Tinker (DB 직접 조작)
php artisan tinker
```

### Laravel Sail (Docker)

```bash
# Sail 시작
./vendor/bin/sail up -d

# Sail 종료
./vendor/bin/sail down

# Sail 내부에서 artisan 실행
./vendor/bin/sail artisan migrate

# Sail 내부에서 composer 실행
./vendor/bin/sail composer install
```

### Chatbot Engine (Python FastAPI)

```bash
# 의존성 설치
cd chatbot-engine
pip install -r requirements.txt

# 개발 서버 실행
uvicorn main:app --reload --port 8001
```

### 캐시 관리

```bash
php artisan config:clear    # 설정 캐시 초기화
php artisan cache:clear     # 앱 캐시 초기화
php artisan route:clear     # 라우트 캐시 초기화
php artisan view:clear      # 뷰 캐시 초기화
php artisan optimize:clear  # 전체 캐시 초기화
```

---

## 10. Known Issues

| 번호 | 심각도 | 내용 |
|------|--------|------|
| #1 | 🔴 높음 | chatbot-engine 미연동 — Laravel과 FastAPI가 완전히 분리된 별개 시스템으로 동작 |
| #2 | 🔴 높음 | chatbot-engine 인증 없음 — FastAPI 엔드포인트에 토큰 검증 미적용 |
| #4 | 🔴 높음 | chatbot-engine 세션 인메모리 저장 — 재시작 시 전체 대화 이력 소실 |
| #5 | 🟡 중간 | TourCastService 미사용 — 서비스 클래스는 존재하지만 실제 연동 없음 |
| #7 | 🟡 중간 | TourCast 인증 취약 (연동 시) — TourCast가 userId 검증을 수행하지 않음 |
| #8 | 🟡 중간 | saveFromChatbot 장소 중복 생성 — 이름 기반 단순 매칭으로 중복 레코드 발생 가능 |
| #9 | 🟢 낮음 | ChatController 테스트 없음 |

---

### #1 🔴 chatbot-engine 미연동

- **파일**: `app/Http/Controllers/Api/ChatController.php`, `chatbot-engine/app/`
- **내용**: Laravel의 `ChatController`는 `openai-php/laravel`로 OpenAI를 직접 호출하고 `chat_messages` 테이블에 대화를 저장한다. `chatbot-engine`(FastAPI)은 LangChain Agent 기반의 완전히 별도 서버로, 두 시스템 간 HTTP 통신이 전혀 없다. `chatbot-engine/.env.example`에 `LARAVEL_SERVICE_TOKEN`이 정의되어 있으나 어떤 코드에서도 사용되지 않는다.
- **현재 동작**: `POST /api/chat` 요청은 Laravel이 직접 처리하며, chatbot-engine은 독립 실행 상태로 미사용 자산이다.
- **해결 방향**: 두 가지 방향 중 선택 필요. (A) `ChatController`가 chatbot-engine으로 HTTP 프록시 — `LARAVEL_SERVICE_TOKEN`을 Bearer 헤더에 포함해 `POST http://chatbot-engine/chat` 호출, 응답을 DB에 저장. (B) chatbot-engine을 제거하고 Laravel 단일 구현으로 통합.

---

### #2 🔴 chatbot-engine 인증 없음

- **파일**: `chatbot-engine/app/api/routes/chat.py`, `chatbot-engine/app/main.py`
- **내용**: FastAPI 라우터 전체에 인증 미들웨어가 없다. `POST /chat`, `GET /chat/{session_id}/history`, `DELETE /chat/{session_id}` 모두 토큰 없이 호출 가능하다.
- **현재 동작**: 네트워크 접근 가능한 누구든 chatbot-engine API를 직접 호출할 수 있어 OpenAI 비용이 무단으로 소모될 수 있다.
- **해결 방향**: FastAPI dependency로 Bearer Token 검증 추가. `chatbot-engine/.env.example`에 이미 `LARAVEL_SERVICE_TOKEN`이 정의되어 있으므로, 해당 값으로 요청 헤더 `Authorization: Bearer {token}`을 검증하는 `verify_token` dependency를 구현.

```python
# 예시
from fastapi import Depends, HTTPException, Security
from fastapi.security import HTTPBearer

security = HTTPBearer()

def verify_token(token = Security(security)):
    if token.credentials != settings.laravel_service_token:
        raise HTTPException(status_code=401, detail="Unauthorized")
```

---

### #4 🔴 chatbot-engine 세션 인메모리 저장

- **파일**: `chatbot-engine/app/services/chat_service.py:36`
- **내용**: LangGraph `MemorySaver()`는 프로세스 메모리에만 세션을 저장한다. 서버 재시작, 컨테이너 교체, 멀티 워커 환경에서 대화 이력이 유실된다. 또한 `clear_session`에서 호출하는 `self._checkpointer.delete_thread(session_id)`는 `MemorySaver`에 존재하지 않는 메서드로, 세션 삭제 시 `AttributeError`가 발생한다.
- **현재 동작**: 단일 프로세스 단기 실행 시에는 동작하나, 재시작하면 모든 세션이 초기화된다.
- **해결 방향**: Redis 또는 PostgreSQL 기반 checkpointer로 교체. `langgraph-checkpoint-redis` 또는 `langgraph-checkpoint-postgres` 패키지 사용.

```python
# Redis checkpointer 예시
from langgraph.checkpoint.redis import RedisSaver
checkpointer = RedisSaver.from_conn_string(settings.redis_url)
```

---

### #5 🟡 TourCastService 미사용

- **파일**: `app/Services/TourCastService.php`, `app/Http/Controllers/Api/PlaceController.php`
- **내용**: `TourCastService`는 완전히 구현되어 있으나 `PlaceController`를 포함한 어떤 컨트롤러에서도 주입·호출되지 않는다. `PlaceController::index()`는 로컬 DB의 `places` 테이블만 조회한다. TourCast Known Issue #5와 동일 맥락의 이슈.
- **현재 동작**: TourCast API가 실제로 호출되지 않아 `places` 테이블에 데이터가 없으면 목록이 빈 상태로 반환된다.
- **해결 방향**: `PlaceController::index()`에서 로컬 DB 미검색 시 fallback으로 `TourCastService::searchTours()`를 호출하거나, 주기적 동기화 Command/Job을 구현해 `places` 테이블을 TourCast 데이터로 채우는 방식 중 선택.

---

### #7 🟡 TourCast 인증 취약 (연동 시)

- **파일**: `app/Services/TourCastService.php:124-126`, `config/services.php:40`
- **내용**: TourCast Known Issue #3에 따르면, TourCast 서버는 API 키만 검증하고 요청 주체(userId)를 검증하지 않는다. `TOURCAST_API_KEY`가 유출되면 임의의 클라이언트가 TravelPlatform을 우회하고 TourCast API를 직접 호출할 수 있다.
- **현재 동작**: TourCastService가 실제로 호출되지 않아 당장 영향은 없으나, #5 이슈 해결 시 즉시 위험에 노출된다.
- **해결 방향**: TourCast 측에 per-request 서명(HMAC) 또는 IP 화이트리스트 도입 요청. TravelPlatform에서는 `TOURCAST_API_KEY`를 환경변수로만 관리하고 코드에 하드코딩하지 않는 현행 방식 유지.

---

### #8 🟡 saveFromChatbot 장소 중복 생성

- **파일**: `app/Http/Controllers/Api/ItineraryController.php:133-145`
- **내용**: 챗봇 일정 저장 시 `LOWER(name)` 완전 일치로만 기존 장소를 검색하므로, AI가 동일 장소를 다른 표기(예: "경복궁" vs "景福宮" vs "Gyeongbokgung")로 반환하면 중복 `places` 레코드가 생성된다. 신규 생성 시 `city`, `country`는 항상 `null`, `category`는 항상 `'attraction'`으로 고정된다.
- **현재 동작**: 동일 장소에 대한 레코드가 여러 개 생성되고, 음식점·숙박 등도 `attraction`으로 분류된다.
- **해결 방향**: 위경도 기반 근접 검색(`ST_DWithin` 또는 거리 공식)을 1차 조건으로, 이름 유사도를 2차 조건으로 사용. AI 응답의 `category` 필드를 `places.category` 값으로 그대로 활용.

---

### #9 🟢 ChatController 테스트 없음

- **파일**: `tests/Feature/Api/` (ChatTest.php 부재)
- **내용**: `tests/Feature/Api/` 폴더에 Auth, Itinerary, ItineraryItem, Place, Profile 테스트는 있으나 Chat 관련 테스트 파일이 없다.
- **현재 동작**: `POST /api/chat`, `GET /api/chat/history`, `DELETE /api/chat/history` 엔드포인트가 테스트 미커버 상태.
- **해결 방향**: `OpenAI` Facade를 Mock하여 실제 API 호출 없이 ChatController 동작을 검증하는 `ChatTest.php` 작성.

```php
// 예시
OpenAI::fake([
    CreateResponse::fake(['choices' => [['message' => ['content' => '{"reply":"테스트 응답","schedule":null}']]]])
]);
```
