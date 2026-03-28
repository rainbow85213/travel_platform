# TravelPlatform

Laravel 12 기반 여행 플랫폼 API 서버입니다. React Native 모바일 앱을 위한 RESTful API를 제공합니다.

## 개발 환경

| 항목 | 버전 |
|------|------|
| PHP | 8.3 |
| Laravel | 12.x |
| Carbon | 3.x |
| PostgreSQL | 17 |
| Redis | alpine |
| openai-php/laravel | - |

## 로컬 개발 환경 구성 (Docker / Laravel Sail)

이 프로젝트는 **Laravel Sail**을 사용한 Docker 기반 개발 환경을 제공합니다.

### 사전 요구사항

- Docker Desktop 또는 **OrbStack** 설치
- PHP 8.2 이상 (로컬 composer 실행 시)

> **참고:** 시스템 기본 PHP가 8.1인 경우, composer 실행 시 PHP 8.3 바이너리를 명시적으로 지정해야 합니다.
> ```bash
> /opt/homebrew/opt/php@8.3/bin/php /usr/local/bin/composer install
> ```

### 설치 및 시작

```bash
# 1. 의존성 설치
/opt/homebrew/opt/php@8.3/bin/php /usr/local/bin/composer install

# 2. 환경 파일 설정
cp .env.example .env

# 3. 컨테이너 시작 (백그라운드)
./vendor/bin/sail up -d

# 4. DB 마이그레이션
./vendor/bin/sail artisan migrate
```

### sail alias 설정 (권장)

`~/.zshrc` 또는 `~/.bashrc`에 추가하면 편리하게 사용할 수 있습니다.

```bash
alias sail='./vendor/bin/sail'
```

적용 후:

```bash
sail up -d
sail artisan migrate
sail artisan tinker
sail composer require [패키지명]
sail npm run dev
sail down
```

### 포트 구성

| 서비스 | 호스트 포트 | 환경변수 |
|--------|------------|---------|
| Laravel | `80` | `APP_PORT` |
| PostgreSQL | `5432` | `FORWARD_DB_PORT` |
| Redis | `6380` | `FORWARD_REDIS_PORT` |
| Vite | `5173` | `VITE_PORT` |

> Redis 포트를 `6380`으로 설정한 이유: 로컬에서 다른 프로젝트의 Redis가 `6379`를 사용 중인 경우 충돌 방지를 위해 `.env`의 `FORWARD_REDIS_PORT=6380`으로 변경하였습니다.

### 주요 Sail 명령어

```bash
sail up -d                        # 컨테이너 백그라운드 시작
sail down                         # 컨테이너 종료
sail artisan [명령어]              # Artisan 명령 실행
sail composer [명령어]             # Composer 명령 실행
sail npm [명령어]                  # NPM 명령 실행
sail shell                        # 컨테이너 bash 접속
sail psql                         # PostgreSQL 접속
sail redis                        # Redis CLI 접속
sail logs                         # 로그 확인
```

---

## API 응답 형식

모든 API 응답은 `app/Traits/ApiResponse.php` trait을 통해 일관된 JSON 형식을 따릅니다.

### 성공 응답

```json
{
    "success": true,
    "message": "성공",
    "data": {}
}
```

### 실패 응답

```json
{
    "success": false,
    "message": "입력값을 확인해 주세요.",
    "data": null,
    "errors": {
        "email": ["이메일 형식이 올바르지 않습니다."]
    }
}
```

### HTTP 상태 코드

| 상태 | 설명 |
|------|------|
| `200` | 성공 |
| `201` | 리소스 생성 성공 |
| `401` | 인증 필요 (토큰 없음 또는 만료) |
| `403` | 접근 권한 없음 (타인의 리소스) |
| `404` | 리소스 없음 |
| `422` | 유효성 검사 실패 |

### 예외 처리

`bootstrap/app.php`에서 API 요청(`api/*`)에 대해 다음 예외를 JSON으로 자동 변환합니다.

| 예외 | 상태 코드 | 메시지 |
|------|-----------|--------|
| `ValidationException` | `422` | 입력값을 확인해 주세요. |
| `AuthenticationException` | `401` | 인증이 필요합니다. |
| `NotFoundHttpException` | `404` | 리소스를 찾을 수 없습니다. |

---

## API 엔드포인트

> 인증이 필요한 엔드포인트는 `Authorization: Bearer {token}` 헤더가 필요합니다.
>
> **모든 요청에 `Accept: application/json` 헤더를 포함해야 JSON 응답을 받을 수 있습니다.**

### 챗봇 (Chat)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `POST` | `/api/chat` | ✓ | AI 여행 플래너 챗봇 메시지 전송 |
| `GET` | `/api/chat/history` | ✓ | 채팅 기록 조회 |
| `DELETE` | `/api/chat/history` | ✓ | 채팅 기록 전체 삭제 |

#### POST /api/chat

**요청 본문:**

```json
{
    "message": "제주도 1박 2일 일정 만들어줘"
}
```

**응답 — 일반 질문:**

```json
{
    "success": true,
    "message": "응답 성공",
    "data": {
        "reply": "제주도는 대한민국 최남단의 아름다운 섬으로..."
    }
}
```

**응답 — 여행 일정 추천 시 (`schedule` 포함):**

```json
{
    "success": true,
    "message": "응답 성공",
    "data": {
        "reply": "제주도 1박 2일 일정을 구성해 드릴게요!...",
        "schedule": [
            {
                "id": "item-1",
                "title": "성산일출봉",
                "latitude": 33.4589,
                "longitude": 126.9425,
                "status": "pending",
                "time": "09:00",
                "scheduledAt": "2026-04-01T09:00:00Z",
                "category": "attraction",
                "description": "유네스코 세계자연유산, 일출 명소",
                "order": 1
            }
        ]
    }
}
```

> - 대화 이력은 서버 DB에 자동 저장되며, 최근 20개 메시지가 OpenAI 컨텍스트로 전달됩니다.
> - `schedule`은 여행 일정 추천 응답일 때만 포함되며, 일반 질문 응답에는 생략됩니다.
> - `category` 값: `restaurant` | `attraction` | `accommodation` | `transport` | `other`

#### GET /api/chat/history

**쿼리 파라미터:**

| 파라미터 | 타입 | 기본값 | 설명 |
|----------|------|--------|------|
| `limit` | integer | 50 | 조회 개수 (최대 200) |
| `before` | ISO 날짜 | - | 페이지네이션 커서 (이 날짜 이전 메시지) |

**응답:**

```json
{
    "success": true,
    "message": "채팅 기록 조회 성공",
    "data": {
        "messages": [
            {
                "id": "1",
                "role": "user",
                "text": "도쿄 1박 2일 일정 추천해줘",
                "schedule": null,
                "createdAt": "2026-03-21T09:00:00+09:00"
            },
            {
                "id": "2",
                "role": "assistant",
                "text": "도쿄 1박 2일 일정을 구성해 드릴게요!",
                "schedule": [...],
                "createdAt": "2026-03-21T09:00:05+09:00"
            }
        ],
        "hasMore": false
    }
}
```

### 인증 (Auth)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `POST` | `/api/auth/register` | - | 회원가입 |
| `POST` | `/api/auth/login` | - | 로그인 |
| `GET` | `/api/auth/me` | ✓ | 내 정보 조회 |
| `POST` | `/api/auth/logout` | ✓ | 현재 기기 로그아웃 |
| `POST` | `/api/auth/logout-all` | ✓ | 전체 기기 로그아웃 |

### 프로필 (Profile)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `GET` | `/api/profile` | ✓ | 프로필 조회 |
| `PUT` | `/api/profile` | ✓ | 프로필 수정 (name, email) |
| `PUT` | `/api/profile/password` | ✓ | 비밀번호 변경 |
| `GET` | `/api/profile/preferences` | ✓ | 선호 설정 조회 |
| `PUT` | `/api/profile/preferences` | ✓ | 선호 설정 수정 |

### 장소 (Places)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `GET` | `/api/places` | ✓ | 장소 목록 (검색/필터, 페이지네이션) |
| `GET` | `/api/places/{place}` | ✓ | 장소 상세 |

**장소 목록 쿼리 파라미터:**

| 파라미터 | 설명 |
|----------|------|
| `search` | 장소명 검색 (대소문자 무관, ilike) |
| `city` | 도시 필터 |
| `country` | 국가 필터 |
| `category` | 카테고리 필터 |

### 여행 일정 (Itineraries)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `GET` | `/api/itineraries` | ✓ | 내 일정 목록 (`?status=draft` 필터 가능) |
| `POST` | `/api/itineraries` | ✓ | 일정 생성 |
| `POST` | `/api/itineraries/save` | ✓ | 챗봇 확정 일정 저장 |
| `GET` | `/api/itineraries/{itinerary}` | ✓ | 일정 상세 (items.place 포함) |
| `PUT` | `/api/itineraries/{itinerary}` | ✓ | 일정 수정 |
| `DELETE` | `/api/itineraries/{itinerary}` | ✓ | 일정 삭제 (SoftDelete) |

### 일정 아이템 (Itinerary Items)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `GET` | `/api/itineraries/{itinerary}/items` | ✓ | 아이템 목록 |
| `POST` | `/api/itineraries/{itinerary}/items` | ✓ | 아이템 추가 |
| `PUT` | `/api/itineraries/{itinerary}/items/{item}` | ✓ | 아이템 수정 |
| `DELETE` | `/api/itineraries/{itinerary}/items/{item}` | ✓ | 아이템 삭제 |

---

## 인증 (Laravel Sanctum)

React Native 모바일 앱을 위한 **API 토큰 기반 인증**을 사용합니다.

### 설정

| 항목 | 값 | 환경변수 |
|------|-----|---------|
| 토큰 만료 | 30일 (43,200분) | `SANCTUM_TOKEN_EXPIRATION` |

### React Native 사용 예시

```js
// 로그인
const res = await fetch('http://localhost/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email, password }),
});
const { success, data } = await res.json();
// data.token, data.user

// 인증이 필요한 요청
fetch('http://localhost/api/itineraries', {
    headers: {
        'Authorization': `Bearer ${data.token}`,
        'Accept': 'application/json',
    },
});
```

---

## 데이터베이스 구조

> 모든 timestamp 컬럼은 Carbon 3.x 권장에 따라 `timestampTz` (timezone 포함) 를 사용합니다.

### ERD

```
users
 ├── hasOne  → user_preferences
 ├── hasMany → itineraries
 │               └── hasMany → itinerary_items
 │                               └── belongsTo → places
 └── hasMany → chat_messages
```

### 테이블 상세

#### `users`
| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | `bigint` | PK |
| `name` | `varchar(255)` | 이름 |
| `email` | `varchar(255)` | 이메일 (unique) |
| `email_verified_at` | `timestamptz` | 이메일 인증 일시 |
| `password` | `varchar(255)` | 비밀번호 (hashed) |
| `remember_token` | `varchar(100)` | 자동 로그인 토큰 |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

#### `user_preferences`
| 컬럼 | 타입 | 기본값 | 설명 |
|------|------|--------|------|
| `id` | `bigint` | | PK |
| `user_id` | `bigint` | | FK → users (unique, cascade) |
| `language` | `varchar(10)` | `ko` | 앱 언어 |
| `currency` | `varchar(10)` | `KRW` | 선호 통화 |
| `timezone` | `varchar(50)` | `Asia/Seoul` | 타임존 |
| `notification_enabled` | `boolean` | `true` | 푸시 알림 여부 |
| `created_at` | `timestamptz` | | |
| `updated_at` | `timestamptz` | | |

#### `places`
| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | `bigint` | PK |
| `name` | `varchar(255)` | 장소명 |
| `description` | `text` | 설명 |
| `address` | `varchar(255)` | 주소 |
| `city` | `varchar(100)` | 도시 |
| `country` | `varchar(100)` | 국가 |
| `latitude` | `numeric(10,7)` | 위도 (약 1cm 정밀도) |
| `longitude` | `numeric(10,7)` | 경도 (약 1cm 정밀도) |
| `category` | `varchar(50)` | 카테고리 (attraction, restaurant …) |
| `thumbnail_url` | `varchar(255)` | 썸네일 URL |
| `rating` | `numeric(3,2)` | 평점 (0.00 ~ 5.00) |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | SoftDelete |

#### `itineraries`
| 컬럼 | 타입 | 기본값 | 설명 |
|------|------|--------|------|
| `id` | `bigint` | | PK |
| `user_id` | `bigint` | | FK → users (cascade) |
| `title` | `varchar(255)` | | 일정 제목 |
| `description` | `text` | | 설명 |
| `start_date` | `date` | | 시작일 |
| `end_date` | `date` | | 종료일 |
| `status` | `varchar(20)` | `draft` | draft / published / archived |
| `created_at` | `timestamptz` | | |
| `updated_at` | `timestamptz` | | |
| `deleted_at` | `timestamptz` | | SoftDelete |

#### `itinerary_items`
| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | `bigint` | PK |
| `itinerary_id` | `bigint` | FK → itineraries (cascade) |
| `place_id` | `bigint` | FK → places (restrict) |
| `day_number` | `smallint` | 몇 일차 |
| `sort_order` | `smallint` | 당일 내 순서 (기본 0) |
| `visited_at` | `timestamptz` | 예정 방문 일시 |
| `duration_minutes` | `smallint` | 예상 체류 시간 (분) |
| `notes` | `text` | 메모 |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

#### `chat_messages`
| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | `bigint` | PK |
| `user_id` | `bigint` | FK → users (cascade) |
| `role` | `enum` | `user` / `assistant` |
| `text` | `text` | 메시지 내용 |
| `schedule` | `json` | 여행 일정 배열 (nullable) |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

### 외래 키 정책

| 관계 | On Delete |
|------|-----------|
| `user_preferences.user_id` → `users.id` | `CASCADE` |
| `itineraries.user_id` → `users.id` | `CASCADE` |
| `itinerary_items.itinerary_id` → `itineraries.id` | `CASCADE` |
| `itinerary_items.place_id` → `places.id` | `RESTRICT` |
| `chat_messages.user_id` → `users.id` | `CASCADE` |

---

## Eloquent 모델

### 모델 관계 구조

```
User
 ├── hasOne  → UserPreference    (preference())
 ├── hasMany → Itinerary         (itineraries())
 └── hasMany → ChatMessage       (chatMessages())

UserPreference
 └── belongsTo → User

Place
 └── hasMany → ItineraryItem     (itineraryItems())

Itinerary
 ├── belongsTo → User
 └── hasMany   → ItineraryItem   (items())  ← day_number, sort_order 정렬

ItineraryItem
 ├── belongsTo → Itinerary
 └── belongsTo → Place

ChatMessage
 └── belongsTo → User
```

### 주요 특이사항

| 모델 | SoftDeletes | 주요 캐스트 |
|------|:-----------:|------------|
| `User` | - | `email_verified_at` → `datetime`, `password` → `hashed` |
| `UserPreference` | - | `notification_enabled` → `boolean` |
| `Place` | ✓ | `latitude/longitude` → `decimal:7`, `rating` → `decimal:2` |
| `Itinerary` | ✓ | `start_date/end_date` → `date` |
| `ItineraryItem` | - | `visited_at` → `datetime` |
| `ChatMessage` | - | `schedule` → `array` |

---

## 테스트

### 실행 방법

```bash
sail artisan test                # 전체 테스트 실행
sail artisan test --coverage     # 커버리지 포함
sail artisan test --filter=Api   # API 테스트만 실행
```

### 테스트 환경

`phpunit.xml`에서 테스트 전용 환경 변수를 설정합니다.

| 항목 | 값 |
|------|-----|
| `APP_ENV` | `testing` |
| `DB_DATABASE` | `testing` (PostgreSQL) |
| `CACHE_STORE` | `array` |
| `SESSION_DRIVER` | `array` |
| `BCRYPT_ROUNDS` | `4` |

> PostgreSQL에 `testing` 데이터베이스가 필요합니다.
> ```bash
> sail psql -c "CREATE DATABASE testing;"
> ```

### 테스트 구조

```
tests/
├── Unit/
│   └── ExampleTest.php
└── Feature/
    ├── Api/
    │   ├── AuthTest.php           # 회원가입, 로그인, me, logout (11개)
    │   ├── ProfileTest.php        # 프로필, 비밀번호, 선호 설정 (11개)
    │   ├── PlaceTest.php          # 장소 목록/검색/필터, 상세 (9개)
    │   ├── ItineraryTest.php      # 일정 CRUD, 권한 체크, 챗봇 저장 (30개)
    │   └── ItineraryItemTest.php  # 아이템 CRUD, 권한 체크 (12개)
    └── TourCastServiceTest.php    # TourCast 서비스 (19개)
```

> 총 **94개** 테스트 통과 (227 assertions)

### 커버리지 결과

| 파일 | 커버리지 |
|------|:-------:|
| `AuthController` | 100% |
| `ChatController` | - |
| `ItineraryController` | 100% |
| `ItineraryItemController` | 100% |
| `PlaceController` | 100% |
| `ProfileController` | 100% |
| `ApiResponse` trait | 87.5% |
| 모델 (6개) | 75 ~ 100% |
| **전체** | **96.8%** |

---

## API 클라이언트 (Bruno)

프로젝트에 [Bruno](https://www.usebruno.com/) 컬렉션이 포함되어 있습니다.

```
bruno/
├── bruno.json
├── environments/
│   ├── local.bru        # http://localhost
│   └── production.bru   # https://travel-platform.fly.dev
├── auth/
│   ├── register.bru     # 회원가입 (토큰 자동 저장)
│   ├── login.bru        # 로그인 (토큰 자동 저장)
│   ├── me.bru
│   └── logout.bru
├── chat/
│   ├── send-message.bru
│   ├── history.bru
│   └── delete-history.bru
├── itineraries/
│   ├── list.bru
│   └── save-from-chat.bru
├── places/
│   └── list.bru
└── profile/
    └── show.bru
```

### 사용 방법

```bash
brew install bruno
```

1. Bruno 실행 → `Open Collection` → `bruno/` 폴더 선택
2. 우측 상단 환경 드롭다운에서 `local` 선택
3. `auth/register` 또는 `auth/login` 실행 → 토큰 자동 저장
4. 이후 모든 인증 요청에 토큰이 자동으로 포함됨

---

### Model Factories

| Factory | 용도 |
|---------|------|
| `UserFactory` | 기본 제공 (Laravel) |
| `PlaceFactory` | 장소 테스트 데이터 |
| `ItineraryFactory` | 일정 테스트 데이터 |
| `ItineraryItemFactory` | 아이템 테스트 데이터 |

---

## 시드 데이터

개발/테스트용 한국 여행지 데이터를 제공합니다.

```bash
sail artisan db:seed --class=PlaceSeeder
```

| 지역 | 장소 |
|------|------|
| 제주 (7) | 성산일출봉, 한라산 국립공원, 만장굴, 섭지코지, 협재해수욕장, 우도, 흑돼지거리 |
| 부산 (4) | 해운대해수욕장, 광안리해수욕장, 감천문화마을, 자갈치시장 |
| 서울 (4) | 경복궁, 북촌한옥마을, 남산서울타워, 명동 |

> `DatabaseSeeder`에서 `PlaceSeeder`를 호출하므로 `sail artisan db:seed`로도 삽입됩니다.

---

## 챗봇 엔진 (chatbot-engine)

Python 기반 AI 여행 플래너 서비스입니다. LangChain + LangGraph + OpenAI를 사용하며, 대화 맥락을 유지하면서 여행 일정을 구조화된 JSON으로 반환합니다.

### 기술 스택

| 항목 | 버전 |
|------|------|
| Python | 3.13 |
| FastAPI | - |
| LangChain | 1.x |
| LangGraph | 1.x |
| langchain-openai | 1.x |

### 설치 및 실행

```bash
cd chatbot-engine

# 가상환경 생성 및 활성화
python3 -m venv venv
source venv/bin/activate

# 의존성 설치
pip install -r requirements.txt

# 환경 파일 설정
cp .env.example .env
# .env 에 OPENAI_API_KEY 입력

# 서버 실행 (기본 포트 8000)
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```

### 환경 변수 (chatbot-engine/.env.example)

| 변수 | 설명 | 기본값 |
|------|------|--------|
| `OPENAI_API_KEY` | OpenAI API 키 | (필수) |
| `OPENAI_MODEL` | 사용 모델 | `gpt-4o-mini` |
| `OPENAI_TEMPERATURE` | 생성 온도 | `0.7` |
| `OPENAI_MAX_TOKENS` | 최대 토큰 | `1024` |
| `LARAVEL_API_URL` | Laravel API URL | `http://localhost/api` |
| `LARAVEL_SERVICE_TOKEN` | Sanctum 서비스 토큰 | - |
| `TOURCAST_BASE_URL` | TourCast API URL | `https://api.tourcast.io/v1` |
| `TOURCAST_API_KEY` | TourCast API 키 | - |
| `ALLOWED_ORIGINS` | CORS 허용 출처 (JSON 배열) | `["http://localhost","http://localhost:8000"]` |

> `chatbot-engine/.env` 는 Laravel `.env` 와 별도 파일입니다. 두 파일을 혼용하지 마세요.

### Laravel API 연동 설정

챗봇이 `search_places` 도구로 Laravel DB를 조회하려면 Sanctum 서비스 토큰이 필요합니다.

```bash
# 1. Laravel Sail 기동
sail up -d

# 2. 서비스 토큰 발급 (tinker)
sail artisan tinker --execute="
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'chatbot@internal.local'],
    ['name' => 'ChatbotService', 'password' => bcrypt('unused')]
);
echo \$user->createToken('chatbot-engine-service')->plainTextToken;
"

# 3. chatbot-engine/.env 에 설정
LARAVEL_API_URL=http://localhost/api
LARAVEL_SERVICE_TOKEN=<발급된_토큰>
```

### API 엔드포인트

| 메서드 | 엔드포인트 | 설명 |
|--------|-----------|------|
| `POST` | `/chat` | 챗봇 메시지 전송 |
| `GET` | `/chat/{session_id}/history` | 대화 이력 조회 |
| `DELETE` | `/chat/{session_id}` | 세션 삭제 |
| `GET` | `/chat/health` | 헬스 체크 |

### 챗봇 응답 형식

#### 일반 대화 (`type: "message"`)

```json
{
    "success": true,
    "message": "성공",
    "data": {
        "session_id": "user-123",
        "type": "message",
        "reply": "제주도 3박 4일 일정을 구성해 드릴게요! 어떤 스타일을 선호하세요?",
        "itinerary": null
    }
}
```

#### 일정 확정 (`type: "itinerary"`)

사용자가 일정에 동의하면 AI가 `finalize_itinerary` 도구를 자동 호출하여 구조화된 JSON을 반환합니다.

```json
{
    "success": true,
    "message": "성공",
    "data": {
        "session_id": "user-123",
        "type": "itinerary",
        "reply": "제주도 1박 2일 일정이 확정되었습니다!",
        "itinerary": [
            {
                "day": 1,
                "time": "09:00",
                "place": "성산일출봉",
                "latitude": 33.4589,
                "longitude": 126.9425,
                "description": "유네스코 세계자연유산, 일출 명소"
            }
        ]
    }
}
```

### 사용 가능한 도구 (Tools)

| 도구 | 설명 |
|------|------|
| `search_places` | Laravel `/api/places` 에서 여행지 검색 |
| `search_tours` | TourCast API 에서 투어 상품 검색 |
| `get_tour_detail` | TourCast API 에서 투어 상세 조회 |
| `finalize_itinerary` | 합의된 여행 일정을 구조화된 JSON으로 확정 |

### 아키텍처

```
POST /chat
  └─ ChatService.chat()
       └─ LangGraph Agent (create_react_agent + MemorySaver)
            ├─ 도구 호출: search_places / search_tours / get_tour_detail
            └─ 일정 확정: finalize_itinerary(items=[{day,time,place,lat,lng,desc},...])
                  └─ messages 에서 tool_calls 추출 → ChatOutput(type="itinerary")
```

---

## 프로덕션 배포 (Fly.io)

### 인프라 구성

| 항목 | 서비스 | 상세 |
|------|--------|------|
| **앱 서버** | Fly.io | 도쿄 리전 (nrt), 512MB RAM |
| **데이터베이스** | Fly Postgres 17 | `travel-platform-db` |
| **캐시 / 세션** | Upstash Redis (TLS) | `curious-newt-71742.upstash.io:6379` |
| **AI** | OpenAI GPT-4o-mini | `OPENAI_API_KEY` |

### 배포 URL

```
https://travel-platform.fly.dev
```

### 환경 변수 (Fly Secrets)

| 변수 | 설명 |
|------|------|
| `APP_KEY` | Laravel 암호화 키 |
| `APP_URL` | 배포 URL |
| `DATABASE_URL` | Fly Postgres 연결 문자열 (자동 주입) |
| `REDIS_HOST` | Upstash Redis 호스트 |
| `REDIS_PASSWORD` | Upstash Redis 비밀번호 |
| `REDIS_SCHEME` | `tls` |
| `REDIS_PORT` | `6379` |
| `OPENAI_API_KEY` | OpenAI API 키 |

### CI/CD (GitHub Actions)

`main` 브랜치에 push하면 자동으로 테스트 → 배포가 실행됩니다.

```
push to main
  └─ Run Tests (PostgreSQL 17 서비스 컨테이너)
       └─ Deploy to Fly.io (테스트 통과 시)
```

GitHub Secrets에 `FLY_API_TOKEN`이 등록되어 있어야 합니다.

```bash
# 토큰 발급
fly tokens create deploy
```

### 수동 배포

```bash
# flyctl 설치
brew install flyctl
fly auth login

# 배포
fly deploy

# 로그 확인
fly logs --app travel-platform

# SSH 접속
fly ssh console --app travel-platform

# 시드 데이터 주입
fly ssh console --app travel-platform -C "php artisan db:seed --force"
```

### Docker 컨테이너 구성

| 파일 | 역할 |
|------|------|
| `Dockerfile` | 멀티스테이지 프로덕션 빌드 (PHP 8.2-fpm + Nginx) |
| `fly.toml` | Fly.io 앱 설정 |
| `docker/nginx.conf` | Nginx 설정 |
| `docker/supervisord.conf` | PHP-FPM + Nginx 동시 실행 |
| `docker/php.ini` | 프로덕션 PHP 설정 (OPcache) |
| `docker/start.sh` | 컨테이너 시작 스크립트 (migrate 포함) |

### 헬스체크

```
GET /health  →  {"status": "ok"}
```

---

## 라이선스

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
