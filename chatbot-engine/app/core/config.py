from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    # App
    app_name: str = "TravelPlatform Chatbot Engine"
    app_version: str = "0.1.0"
    debug: bool = False

    # OpenAI
    openai_api_key: str = ""
    openai_model: str = "gpt-4o-mini"
    openai_temperature: float = 0.7
    openai_max_tokens: int = 1024

    # Laravel API (내부 통신)
    laravel_api_url: str = "http://localhost/api"
    laravel_api_timeout: int = 10
    laravel_service_token: str = ""  # Sanctum 서비스 토큰 (chatbot → Laravel)

    # TourCast API (직접 호출)
    tourcast_base_url: str = "https://api.tourcast.io/v1"
    tourcast_api_key: str = ""
    tourcast_timeout: int = 10


settings = Settings()
