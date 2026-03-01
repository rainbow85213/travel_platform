# TravelPlatform

Laravel 12 기반 여행 플랫폼 프로젝트입니다.

## 개발 환경

| 항목 | 버전 |
|------|------|
| PHP | 8.3 |
| Laravel | 12.x |
| Carbon | 3.x |
| PostgreSQL | 17 |
| Redis | alpine |

## 로컬 개발 환경 구성 (Docker / Laravel Sail)

이 프로젝트는 **Laravel Sail**을 사용한 Docker 기반 개발 환경을 제공합니다.

### 사전 요구사항

- Docker Desktop 설치
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

## 인증 (Laravel Sanctum)

React Native 모바일 앱을 위한 **API 토큰 기반 인증**을 사용합니다.

### 설정

| 항목 | 값 | 환경변수 |
|------|-----|---------|
| 토큰 만료 | 30일 (43,200분) | `SANCTUM_TOKEN_EXPIRATION` |

### API 엔드포인트

| 메서드 | 엔드포인트 | 인증 | 설명 |
|--------|-----------|:----:|------|
| `POST` | `/api/auth/register` | - | 회원가입 |
| `POST` | `/api/auth/login` | - | 로그인 |
| `GET` | `/api/auth/me` | ✓ | 내 정보 조회 |
| `POST` | `/api/auth/logout` | ✓ | 현재 기기 로그아웃 |
| `POST` | `/api/auth/logout-all` | ✓ | 전체 기기 로그아웃 |

### 응답 형식

```json
{
    "token": "1|xxxxxxxxxxxxxx",
    "user": {
        "id": 1,
        "name": "홍길동",
        "email": "user@example.com"
    }
}
```

### React Native 사용 예시

```js
// 로그인
const res = await fetch('http://localhost/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email, password }),
});
const { token, user } = await res.json();

// 인증이 필요한 요청
fetch('http://localhost/api/auth/me', {
    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
});
```

---

## 데이터베이스 구조

> 모든 timestamp 컬럼은 Carbon 3.x 권장에 따라 `timestampTz` (timezone 포함) 를 사용합니다.

### ERD

```
users
 ├── hasOne  → user_preferences
 └── hasMany → itineraries
                 └── hasMany → itinerary_items
                                 └── belongsTo → places
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

### 외래 키 정책

| 관계 | On Delete |
|------|-----------|
| `user_preferences.user_id` → `users.id` | `CASCADE` |
| `itineraries.user_id` → `users.id` | `CASCADE` |
| `itinerary_items.itinerary_id` → `itineraries.id` | `CASCADE` |
| `itinerary_items.place_id` → `places.id` | `RESTRICT` |

---

## Eloquent 모델

### 모델 관계 구조

```
User
 ├── hasOne  → UserPreference    (preference())
 └── hasMany → Itinerary         (itineraries())

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
```

### 주요 특이사항

| 모델 | SoftDeletes | 주요 캐스트 |
|------|:-----------:|------------|
| `User` | - | `email_verified_at` → `datetime`, `password` → `hashed` |
| `UserPreference` | - | `notification_enabled` → `boolean` |
| `Place` | ✓ | `latitude/longitude` → `decimal:7`, `rating` → `decimal:2` |
| `Itinerary` | ✓ | `start_date/end_date` → `date` |
| `ItineraryItem` | - | `visited_at` → `datetime` |

---

## 라이선스

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
