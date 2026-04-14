from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field


class ItineraryItem(BaseModel):
    day: int = Field(..., ge=1, description="일차 (1부터 시작)")
    time: str = Field(..., description="시간 (예: '09:00', '14:30')")
    title: str = Field(..., description="장소명")
    latitude: float = Field(..., ge=-90, le=90, description="위도")
    longitude: float = Field(..., ge=-180, le=180, description="경도")
    description: str = Field(..., description="장소에 대한 간단한 설명")
    category: str = Field(
        default="attraction",
        description="카테고리: attraction | restaurant | accommodation | transport | other",
    )


class FinalizeItineraryInput(BaseModel):
    items: list[ItineraryItem] = Field(
        ...,
        description="확정된 여행 일정 항목 목록 (방문 순서대로)",
    )


class ChatOutput(BaseModel):
    type: Literal["message", "itinerary"]
    message: str
    itinerary: list[ItineraryItem] | None = None
