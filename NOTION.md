# TravelPlatform — 프로젝트 문서

> React Native 모바일 여행 플래닝 앱을 위한 AI 기반 백엔드 API 서버

---

## 프로젝트 개요

TravelPlatform은 **AI 챗봇으로 여행 일정을 자동 생성**하고, 사용자가 일정을 저장·관리할 수 있는 RESTful API 서버입니다.

| 항목 | 내용 |
|------|------|
| **서비스 URL** | https://travel-platform.fly.dev |
| **GitHub** | https://github.com/rainbow85213/travel_platform |
| **배포 환경** | Fly.io (도쿄 리전) |
| **API 문서** | 이 문서 하단 참고 |

---

## 기술 스택

| 구분 | 기술 |
|------|------|
| **백엔드** | Laravel 12 / PHP 8.2 |
| **데이터베이스** | PostgreSQL 17 |
| **캐시 / 세션** | Redis (Upstash, TLS) |
| **인증** | Laravel Sanctum (Bearer Token) |
| **AI** | OpenAI GPT-4o-mini |
| **배포** | Fly.io (Docker) |
| **CI/CD** | GitHub Actions |
| **테스트** | PHPUnit 11 (73개, 96.8% 커버리지) |

---

## 시스템 아키텍처

```
React Native 앱
      │
      ▼
Fly.io (travel-platform.fly.dev)
  ├─ Nginx + PHP-FPM (Docker)
  ├─ Laravel 12 API
  │    ├─ Sanctum 인증
  │    ├─ OpenAI 챗봇
  │    └─ TourCast 연동
      │
  ├─ Fly Postgres 17 (DB)
  └─ Upstash Redis (캐시/세션)
```

---

## 핵심 기능

### 1. AI 챗봇 일정 생성
- OpenAI GPT-4o-mini 기반 한국어 여행 플래너
- 클라이언트가 대화 이력 관리 (서버 무상태)
- 챗봇이 확정한 일정을 DB에 자동 저장

### 2. 여행 일정 관리
- 일정(Itinerary) CRUD
- 일정 아이템(장소, 방문 시간, 소요 시간) 관리
- 상태 관리: draft → published → archived

### 3. 장소 데이터
- 한국 여행지 15개 기본 제공 (제주, 부산, 서울)
- 검색 / 도시 / 카테고리 필터
- TourCast API 연동으로 추가 데이터 수집 가능

### 4. 사용자 인증
- 회원가입 / 로그인 / 로그아웃
- 멀티 디바이스 토큰 관리
- 사용자 선호 설정 (언어, 통화, 타임존)

---

## API 엔드포인트

> **Base URL:** `https://travel-platform.fly.dev`
>
> 인증 필요 엔드포인트: `Authorization: Bearer {token}` 헤더 필수
>
> 모든 요청에 `Accept: application/json` 헤더 포함 권장

### 인증 (Auth)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| POST | `/api/auth/register` | - | 회원가입 |
| POST | `/api/auth/login` | - | 로그인 |
| GET | `/api/auth/me` | ✓ | 내 정보 |
| POST | `/api/auth/logout` | ✓ | 현재 기기 로그아웃 |
| POST | `/api/auth/logout-all` | ✓ | 전체 기기 로그아웃 |

**회원가입 예시:**
```json
POST /api/auth/register
{
  "name": "홍길동",
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```

**응답:**
```json
{
  "success": true,
  "message": "회원가입이 완료되었습니다.",
  "data": {
    "token": "1|xxxxx",
    "user": { "id": 1, "name": "홍길동", "email": "user@example.com" }
  }
}
```

---

### 챗봇 (Chat)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| POST | `/api/chat` | - | AI 여행 플래너 메시지 전송 |

**요청:**
```json
{
  "message": "제주도 2박 3일 여행 일정 만들어줘",
  "history": [
    { "role": "user", "content": "이전 메시지" },
    { "role": "assistant", "content": "이전 응답" }
  ]
}
```

**응답:**
```json
{
  "success": true,
  "data": {
    "reply": "제주도 2박 3일 일정을 구성해 드릴게요!..."
  }
}
```

---

### 장소 (Places)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| GET | `/api/places` | ✓ | 장소 목록 |
| GET | `/api/places/{id}` | ✓ | 장소 상세 |

**쿼리 파라미터:**

| 파라미터 | 예시 | 설명 |
|----------|------|------|
| `search` | `?search=해수욕장` | 장소명 검색 |
| `city` | `?city=제주` | 도시 필터 |
| `country` | `?country=KR` | 국가 필터 |
| `category` | `?category=attraction` | 카테고리 필터 |

**카테고리 목록:** `attraction` `restaurant` `cafe` `hotel` `shopping`

---

### 여행 일정 (Itineraries)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| GET | `/api/itineraries` | ✓ | 내 일정 목록 |
| POST | `/api/itineraries` | ✓ | 일정 생성 |
| POST | `/api/itineraries/save` | ✓ | 챗봇 확정 일정 저장 |
| GET | `/api/itineraries/{id}` | ✓ | 일정 상세 |
| PUT | `/api/itineraries/{id}` | ✓ | 일정 수정 |
| DELETE | `/api/itineraries/{id}` | ✓ | 일정 삭제 |

**일정 생성 예시:**
```json
POST /api/itineraries
{
  "title": "제주 봄 여행",
  "description": "가족과 함께하는 제주 여행",
  "start_date": "2026-04-01",
  "end_date": "2026-04-03"
}
```

**챗봇 일정 저장 (`/save`) 예시:**
```json
POST /api/itineraries/save
{
  "title": "제주 1박 2일",
  "start_date": "2026-04-01",
  "itinerary": [
    {
      "day": 1,
      "time": "09:00",
      "place": "성산일출봉",
      "latitude": 33.4589,
      "longitude": 126.9425,
      "description": "유네스코 세계자연유산"
    }
  ]
}
```

---

### 일정 아이템 (Itinerary Items)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| GET | `/api/itineraries/{id}/items` | ✓ | 아이템 목록 |
| POST | `/api/itineraries/{id}/items` | ✓ | 아이템 추가 |
| PUT | `/api/itineraries/{id}/items/{item}` | ✓ | 아이템 수정 |
| DELETE | `/api/itineraries/{id}/items/{item}` | ✓ | 아이템 삭제 |

---

### 프로필 (Profile)

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| GET | `/api/profile` | ✓ | 프로필 조회 |
| PUT | `/api/profile` | ✓ | 프로필 수정 |
| PUT | `/api/profile/password` | ✓ | 비밀번호 변경 |
| GET | `/api/profile/preferences` | ✓ | 선호 설정 조회 |
| PUT | `/api/profile/preferences` | ✓ | 선호 설정 수정 |

---

## API 응답 형식

### 성공
```json
{
  "success": true,
  "message": "성공",
  "data": { ... }
}
```

### 실패
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

| 코드 | 의미 |
|------|------|
| 200 | 성공 |
| 201 | 생성 성공 |
| 401 | 인증 필요 (토큰 없음 또는 만료) |
| 403 | 접근 권한 없음 (타인의 리소스) |
| 404 | 리소스 없음 |
| 422 | 유효성 검사 실패 |

---

## Postman 테스트 가이드

### 1. 환경변수 설정

`Environments` → `New` 생성 후:

| Variable | Value |
|----------|-------|
| `base_url` | `https://travel-platform.fly.dev` |
| `token` | (로그인 후 복사) |

### 2. 토큰 발급

```
POST {{base_url}}/api/auth/login
Body (raw JSON):
{
  "email": "test@example.com",
  "password": "password"
}
```

응답의 `data.token` 값을 `token` 환경변수에 저장

### 3. 인증 헤더 설정

`Authorization` 탭 → `Type: Bearer Token` → `{{token}}`

### 4. 테스트 순서 (권장)

1. `POST /api/auth/register` — 회원가입
2. `GET /api/places` — 여행지 목록 확인
3. `POST /api/chat` — 챗봇 일정 생성
4. `POST /api/itineraries/save` — 일정 저장
5. `GET /api/itineraries` — 저장된 일정 확인

---

## 데이터베이스 구조

```
users (1) ──────────── user_preferences (1)
  │
  └── itineraries (N)
            │
            └── itinerary_items (N) ──── places (1)
```

### 시드 데이터 (한국 여행지 15개)

| 지역 | 장소 |
|------|------|
| 제주 (7곳) | 성산일출봉, 한라산 국립공원, 만장굴, 섭지코지, 협재해수욕장, 우도, 흑돼지거리 |
| 부산 (4곳) | 해운대해수욕장, 광안리해수욕장, 감천문화마을, 자갈치시장 |
| 서울 (4곳) | 경복궁, 북촌한옥마을, 남산서울타워, 명동 |

---

## 배포 정보

| 항목 | 내용 |
|------|------|
| **플랫폼** | Fly.io |
| **리전** | nrt (도쿄) |
| **서버 사양** | 1 vCPU / 512MB RAM |
| **데이터베이스** | Fly Postgres 17 |
| **Redis** | Upstash (TLS, 무료) |
| **헬스체크** | `GET /health` → `{"status":"ok"}` |

### CI/CD 파이프라인

```
git push origin main
  └─ GitHub Actions
       ├─ 1. 테스트 실행 (PostgreSQL 17)
       └─ 2. 테스트 통과 시 Fly.io 자동 배포
```

---

## 로컬 개발 환경

```bash
# 1. 의존성 설치
composer install

# 2. 환경 설정
cp .env.example .env

# 3. Docker 시작 (Laravel Sail)
./vendor/bin/sail up -d

# 4. DB 마이그레이션 + 시드
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed

# 5. 테스트 실행
./vendor/bin/sail artisan test
```

---

## 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-03-15 | Fly.io 프로덕션 배포 완료 (Postgres, Upstash Redis, GitHub Actions CI/CD) |
| 2026-03-07 | ChatController 추가 (OpenAI GPT-4o-mini) |
| 2026-03-07 | POST /api/itineraries/save 챗봇 일정 저장 엔드포인트 추가 |
| 2026-03-02 | 한국 여행지 시드 데이터 15개 추가 |
| 2026-03-02 | 초기 API 서버 구축 (Auth, Profile, Places, Itineraries) |
