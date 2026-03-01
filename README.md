# TravelPlatform

Laravel 12 기반 여행 플랫폼 프로젝트입니다.

## 개발 환경

| 항목 | 버전 |
|------|------|
| PHP | 8.3 |
| Laravel | 12.x |
| Carbon | 3.x |
| MySQL | 8.4 |
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
| MySQL | `3306` | `FORWARD_DB_PORT` |
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
sail mysql                        # MySQL 접속
sail redis                        # Redis CLI 접속
sail logs                         # 로그 확인
```

## 라이선스

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
