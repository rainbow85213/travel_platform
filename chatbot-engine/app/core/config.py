from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
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


settings = Settings()
