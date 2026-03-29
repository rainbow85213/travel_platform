from fastapi import HTTPException, Security
from fastapi.security import HTTPBearer

from app.core.config import settings

security = HTTPBearer()


def verify_token(credentials=Security(security)) -> None:
    """LARAVEL_SERVICE_TOKEN과 Bearer 토큰을 비교해 인증합니다."""
    if credentials.credentials != settings.laravel_service_token:
        raise HTTPException(status_code=401, detail="Unauthorized")
