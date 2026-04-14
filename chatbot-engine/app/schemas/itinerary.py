from __future__ import annotations

import re
from typing import Literal

from pydantic import BaseModel, Field, field_validator

# 한국어 시간 표현 → HH:MM 변환 테이블
_KO_TIME_MAP: dict[str, str] = {
    "새벽": "04:00",
    "아침": "08:00",
    "오전": "09:00",
    "점심": "12:00",
    "오후": "14:00",
    "저녁": "18:00",
    "밤":   "20:00",
    "숙박": "21:00",
    "야간": "21:00",
}

_HH_MM_RE = re.compile(r"^\d{2}:\d{2}$")
_H_MM_RE  = re.compile(r"^(\d):(\d{2})$")


class ItineraryItem(BaseModel):
    day: int = Field(..., ge=1, description="일차 (1부터 시작)")
    time: str = Field(..., description="시간 — 반드시 HH:MM 24시간 형식 (예: '09:00', '14:30')")
    title: str = Field(..., description="장소명")
    latitude: float = Field(..., ge=-90, le=90, description="위도")
    longitude: float = Field(..., ge=-180, le=180, description="경도")
    description: str = Field(..., description="장소에 대한 간단한 설명")
    category: str = Field(
        default="attraction",
        description="카테고리: attraction | restaurant | accommodation | transport | other",
    )

    @field_validator("time", mode="before")
    @classmethod
    def normalize_time(cls, v: str) -> str:
        """
        HH:MM 형식이 아닌 time 값을 정규화한다.
        - "9:00" → "09:00"  (한 자리 시)
        - "점심", "저녁" 등 한국어 → 대응 HH:MM
        - 이미 올바른 형식이면 그대로 반환
        """
        v = str(v).strip()

        # 이미 HH:MM 형식
        if _HH_MM_RE.match(v):
            return v

        # H:MM → HH:MM (한 자리 시)
        m = _H_MM_RE.match(v)
        if m:
            return f"{int(m.group(1)):02d}:{m.group(2)}"

        # 한국어 시간 표현 (부분 일치 포함)
        for ko, hhmm in _KO_TIME_MAP.items():
            if ko in v:
                return hhmm

        # 매핑 없으면 기본값 "09:00" (TourCast validation 통과 우선)
        return "09:00"


class FinalizeItineraryInput(BaseModel):
    items: list[ItineraryItem] = Field(
        ...,
        description="확정된 여행 일정 항목 목록 (방문 순서대로)",
    )


class ChatOutput(BaseModel):
    type: Literal["message", "itinerary"]
    message: str
    itinerary: list[ItineraryItem] | None = None
