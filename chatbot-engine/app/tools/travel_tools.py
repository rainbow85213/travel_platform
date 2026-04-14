"""
LangChain Tools — 여행 관련 외부 API 호출 도구

- search_places          : Laravel /api/places          (Laravel DB 장소 검색)
- search_tourist_spots   : TourCast /tourist-spots       (관광지 검색)
- get_tourist_spot_detail: TourCast /tourist-spots/{id}  (관광지 상세)
- search_accommodations  : TourCast /api/accommodations  (숙박 검색)
- search_nearby_places   : TourCast /api/spots/nearby    (현위치 기반 검색)
- finalize_itinerary     : 일정 확정
"""
import re

import httpx
from langchain_core.tools import tool

from app.core.config import settings
from app.schemas.itinerary import FinalizeItineraryInput, ItineraryItem


# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------

def _laravel_headers() -> dict:
    headers: dict = {"Accept": "application/json"}
    if settings.laravel_service_token:
        headers["Authorization"] = f"Bearer {settings.laravel_service_token}"
    return headers


def _tourcast_root_url() -> str:
    """tourcast_base_url에서 /v1 suffix를 제거해 root URL 반환."""
    return re.sub(r'/v\d+$', '', settings.tourcast_base_url.rstrip('/'))


# ---------------------------------------------------------------------------
# Tools
# ---------------------------------------------------------------------------

@tool
def search_places(
    query: str = "",
    city: str = "",
    country: str = "",
    category: str = "",
) -> str:
    """
    Laravel 플랫폼에 등록된 여행지(장소)를 검색합니다.

    Args:
        query:    장소 이름 키워드 (예: "해운대", "에펠탑")
        city:     도시 이름 (예: "부산", "파리")
        country:  국가 이름 (예: "한국", "프랑스")
        category: 카테고리 (예: "beach", "mountain", "city", "cultural", "food")

    Returns:
        검색된 장소 목록 (최대 5개) 또는 오류 메시지
    """
    if not any([query, city, country, category]):
        return "검색 조건을 하나 이상 입력해 주세요."

    params: dict = {}
    if query:
        params["search"] = query
    if city:
        params["city"] = city
    if country:
        params["country"] = country
    if category:
        params["category"] = category

    try:
        with httpx.Client(timeout=settings.laravel_api_timeout) as client:
            resp = client.get(
                f"{settings.laravel_api_url}/places",
                params=params,
                headers=_laravel_headers(),
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"장소 검색 오류 (HTTP {e.response.status_code}): {e.response.text[:200]}"
    except httpx.RequestError as e:
        return f"Laravel API 연결 실패: {e}"

    places = body.get("data", {}).get("data", [])
    if not places:
        return "해당 조건에 맞는 장소를 찾을 수 없습니다."

    lines: list[str] = []
    for p in places[:5]:
        rating = p.get("rating")
        rating_str = f" | ★ {rating}" if rating else ""
        lines.append(
            f"• [{p['id']}] {p['name']} — {p.get('city', '')}, {p.get('country', '')}"
            f" | {p.get('category', '')}{rating_str}"
        )
        desc = (p.get("description") or "").strip()
        if desc:
            lines.append(f"  {desc[:100]}")

    return "\n".join(lines)


@tool
def search_tourist_spots(keyword: str = "", city: str = "", limit: int = 10) -> str:
    """
    TourCast DB에서 관광지를 키워드 또는 도시명으로 검색합니다.
    특정 지역의 볼거리, 관광지, 명소를 찾을 때 사용하세요.

    Args:
        keyword: 검색 키워드 (예: "고양", "경복궁", "해운대"). 장소명이나 주소에서 검색.
        city:    도시명 필터 (예: "고양시", "부산시", "제주시"). keyword와 함께 또는 단독 사용.
        limit:   최대 결과 수 (기본값 10, 최대 20)

    Returns:
        관광지 목록 또는 오류 메시지
    """
    if not keyword and not city:
        return "keyword 또는 city 중 하나는 반드시 입력해 주세요."

    params: dict = {"limit": min(limit, 20)}
    if keyword:
        params["keyword"] = keyword
    if city:
        params["city"] = city

    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(
                f"{_tourcast_root_url()}/tourist-spots",
                params=params,
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"관광지 검색 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    items = body.get("items", [])
    total = body.get("total", 0)
    if not items:
        return f"'{keyword or city}' 관련 관광지를 찾을 수 없습니다."

    lines = [f"[총 {total}건 중 {len(items)}건 표시]"]
    for item in items:
        lat = item.get("mapY") or ""
        lng = item.get("mapX") or ""
        coord = f" | 좌표: {lat}, {lng}" if lat and lng else ""
        lines.append(
            f"• [ID:{item.get('id')}] {item.get('title', '이름 없음')}\n"
            f"  주소: {item.get('address') or '정보 없음'}{coord}"
        )
    return "\n".join(lines)


@tool
def get_tourist_spot_detail(spot_id: int) -> str:
    """
    TourCast DB에서 특정 관광지의 상세 정보를 조회합니다.
    search_tourist_spots 결과의 ID 값을 사용하세요.

    Args:
        spot_id: 관광지 ID (search_tourist_spots 결과의 ID)

    Returns:
        관광지 상세 정보 또는 오류 메시지
    """
    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(f"{_tourcast_root_url()}/tourist-spots/{spot_id}")
            resp.raise_for_status()
            spot = resp.json()
    except httpx.HTTPStatusError as e:
        return f"관광지 조회 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    lines = [
        f"장소명: {spot.get('title', '')}",
        f"주소: {spot.get('address') or '정보 없음'}",
    ]
    if spot.get("overview"):
        lines.append(f"설명: {spot['overview'][:200]}")
    if spot.get("mapY") and spot.get("mapX"):
        lines.append(f"좌표: 위도 {spot['mapY']}, 경도 {spot['mapX']}")
    if spot.get("image"):
        lines.append(f"이미지: {spot['image']}")
    return "\n".join(lines)


@tool
def search_accommodations(keyword: str = "", city: str = "", limit: int = 10) -> str:
    """
    TourCast DB에서 숙박 시설을 키워드 또는 도시명으로 검색합니다.
    호텔, 펜션, 모텔, 게스트하우스 등 숙박 정보를 찾을 때 사용하세요.

    Args:
        keyword: 검색 키워드 (예: "고양", "펜션", "호텔")
        city:    도시명 필터 (예: "고양시", "부산시")
        limit:   최대 결과 수 (기본값 10)

    Returns:
        숙박 시설 목록 또는 오류 메시지
    """
    if not keyword and not city:
        return "keyword 또는 city 중 하나는 반드시 입력해 주세요."

    params: dict = {"limit": min(limit, 20)}
    if keyword:
        params["keyword"] = keyword
    if city:
        params["city"] = city

    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(
                f"{_tourcast_root_url()}/api/accommodations",
                params=params,
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"숙박 검색 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    items = body.get("items", [])
    if not items:
        return f"'{keyword or city}' 관련 숙박 시설을 찾을 수 없습니다."

    lines = [f"[총 {body.get('total', 0)}건 중 {len(items)}건 표시]"]
    for item in items:
        tel = f" | 전화: {item['tel']}" if item.get("tel") else ""
        lines.append(
            f"• [ID:{item.get('id')}] {item.get('title', '이름 없음')}\n"
            f"  주소: {item.get('address') or '정보 없음'}{tel}"
        )
    return "\n".join(lines)


@tool
def search_nearby_places(
    lat: float,
    lng: float,
    radius: float = 3.0,
    limit: int = 10,
    type: str = "all",
) -> str:
    """
    사용자의 현재 위치(위도/경도) 기준으로 반경 내 관광지와 숙박을 검색합니다.
    '근처', '주변', '가까운 곳' 등 위치 기반 요청에 반드시 사용하세요.

    Args:
        lat:    사용자 현재 위도 (시스템 프롬프트에서 제공된 값 사용)
        lng:    사용자 현재 경도 (시스템 프롬프트에서 제공된 값 사용)
        radius: 검색 반경 km (기본값 3, 최대 20)
        limit:  최대 결과 수 (기본값 10)
        type:   'tourist_spots' | 'accommodations' | 'all' (기본값 'all')

    Returns:
        반경 내 장소 목록 (거리 오름차순) 또는 오류 메시지
    """
    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(
                f"{_tourcast_root_url()}/api/spots/nearby",
                params={"lat": lat, "lng": lng, "radius": radius,
                        "limit": limit, "type": type},
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"근처 장소 검색 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    items = body.get("items", [])
    if not items:
        return f"반경 {radius}km 내에 검색 결과가 없습니다."

    lines = [f"[현재 위치 기준 반경 {body.get('radiusKm', radius)}km 내 장소]"]
    for item in items:
        cat = "관광지" if item.get("category") == "tourist_spot" else "숙박"
        dist = item.get("distanceKm", "?")
        lines.append(
            f"• {item.get('title', '이름 없음')} ({cat}) — {dist}km\n"
            f"  주소: {item.get('address') or '정보 없음'}\n"
            f"  좌표: {item.get('lat')}, {item.get('lng')}"
        )
    return "\n".join(lines)


@tool(args_schema=FinalizeItineraryInput)
def finalize_itinerary(items: list[ItineraryItem]) -> str:
    """
    사용자와 합의된 여행 일정을 최종 확정합니다.
    모든 방문 장소의 일차·시간·장소명·위도·경도·설명을 포함해야 합니다.
    일정이 완전히 확정되기 전에는 호출하지 마세요.
    """
    return f"여행 일정 {len(items)}개 장소가 확정되었습니다."
