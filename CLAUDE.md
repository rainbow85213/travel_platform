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
| LangGraph | `>=0.2.0` |
| langgraph-checkpoint-redis | `>=0.0.1` (Redis 세션 영속화) |
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

### Chatbot Engine 연동 방식

- `ChatController`는 OpenAI를 직접 호출하지 않고, **chatbot-engine(FastAPI)**으로 HTTP 프록시
- 설정: `config/services.php`의 `chatbot_engine` 키 (`CHATBOT_ENGINE_URL`, `LARAVEL_SERVICE_TOKEN`)
- 요청 흐름: `POST /api/chat` → `Http::withToken(...)→post(chatbot-engine/chat, [session_id, message])` → `chat_messages` 테이블에 저장
- session_id 규칙: `'user_' . $user->id` (사용자별 대화 맥락 분리)
- chatbot-engine 응답에 `itinerary` 배열이 포함되면 `data.schedule` 키로 클라이언트에 반환
- 내부 오류(`5xx`, 연결 실패)는 로그만 남기고 클라이언트에 일반 오류 메시지 반환

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

### 일정 (TourCast 프록시)

> userId는 인증된 사용자 기준으로 자동 주입 (`user_{id}`)

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/schedule/map` | 지도 아이템 조회 (date, filters 쿼리) |
| GET | `/api/schedule/route` | 경로 조회 (date 쿼리) |
| GET | `/api/schedule/heatmap` | 히트맵 조회 |
| GET | `/api/schedule/list` | 여행 플랜 목록 (page, limit 쿼리) |
| POST | `/api/schedule` | 여행 플랜 생성 (title, sourceText, items[]) |
| PATCH | `/api/schedule/item/{itemId}` | 아이템 상태 변경 (status) |

### 디바이스

| Method | Path | 설명 |
|--------|------|------|
| POST | `/api/user/device-token` | FCM 디바이스 토큰 저장 (device_token, device_platform) |

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

### Chatbot Engine 연동

```env
CHATBOT_ENGINE_URL=http://localhost:8001  # chatbot-engine FastAPI 서버 URL
LARAVEL_SERVICE_TOKEN=                    # chatbot-engine Bearer 인증 토큰 (두 .env에 동일 값 설정)
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

잔여 Known Issues 없음.

