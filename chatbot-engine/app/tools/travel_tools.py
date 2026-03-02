"""
LangChain Tools — 여행 관련 외부 API 호출 도구

- search_places   : Laravel /api/places  (여행지 검색)
- search_tours    : TourCast /tours       (투어 상품 검색)
- get_tour_detail : TourCast /tours/{id}  (투어 상세 조회)
"""
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


def _tourcast_headers() -> dict:
    return {"X-Api-Key": settings.tourcast_api_key, "Accept": "application/json"}


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
def search_tours(keyword: str, page: int = 1) -> str:
    """
    TourCast API에서 여행 상품(투어)을 키워드로 검색합니다.

    Args:
        keyword: 검색 키워드 (예: "제주도", "오사카 맛집 투어", "유럽 배낭")
        page:    페이지 번호 (기본값 1)

    Returns:
        투어 상품 목록 (최대 5개) 또는 오류 메시지
    """
    if not settings.tourcast_api_key:
        return "TourCast API 키가 설정되지 않았습니다. (.env TOURCAST_API_KEY 확인)"

    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(
                f"{settings.tourcast_base_url}/tours",
                params={"keyword": keyword, "page": page},
                headers=_tourcast_headers(),
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"투어 검색 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    # TourCast 응답 형식: list 또는 {data: [...]} 또는 {tours: [...]}
    if isinstance(body, list):
        tours = body
    else:
        tours = body.get("data") or body.get("tours") or []

    if not tours:
        return f"'{keyword}' 관련 투어 상품을 찾을 수 없습니다."

    lines: list[str] = []
    for t in tours[:5]:
        name = t.get("name") or t.get("title", "이름 없음")
        tid = t.get("id", "")
        price = t.get("price")
        price_str = f" | {price}" if price else ""
        lines.append(f"• [ID:{tid}] {name}{price_str}")
        desc = str(t.get("description") or "").strip()
        if desc:
            lines.append(f"  {desc[:100]}")

    return "\n".join(lines)


@tool
def get_tour_detail(tour_id: str) -> str:
    """
    TourCast API에서 특정 투어 상품의 상세 정보를 조회합니다.

    Args:
        tour_id: 투어 상품 ID (search_tours 결과의 ID 값)

    Returns:
        투어 상세 정보 또는 오류 메시지
    """
    if not settings.tourcast_api_key:
        return "TourCast API 키가 설정되지 않았습니다. (.env TOURCAST_API_KEY 확인)"

    try:
        with httpx.Client(timeout=settings.tourcast_timeout) as client:
            resp = client.get(
                f"{settings.tourcast_base_url}/tours/{tour_id}",
                headers=_tourcast_headers(),
            )
            resp.raise_for_status()
            body = resp.json()
    except httpx.HTTPStatusError as e:
        return f"투어 상세 조회 오류 (HTTP {e.response.status_code})"
    except httpx.RequestError as e:
        return f"TourCast API 연결 실패: {e}"

    tour = body.get("data", body) if isinstance(body, dict) else {}
    if not tour:
        return f"ID '{tour_id}'에 해당하는 투어를 찾을 수 없습니다."

    lines: list[str] = [
        f"투어명  : {tour.get('name') or tour.get('title', '')}",
        f"ID      : {tour.get('id', tour_id)}",
    ]
    if tour.get("description"):
        lines.append(f"설명    : {tour['description']}")
    if tour.get("price"):
        lines.append(f"가격    : {tour['price']}")
    if tour.get("duration"):
        lines.append(f"기간    : {tour['duration']}")
    if tour.get("location") or tour.get("city"):
        lines.append(f"위치    : {tour.get('location') or tour.get('city', '')}")
    if tour.get("rating"):
        lines.append(f"평점    : ★ {tour['rating']}")

    return "\n".join(lines)


@tool(args_schema=FinalizeItineraryInput)
def finalize_itinerary(items: list[ItineraryItem]) -> str:
    """
    사용자와 합의된 여행 일정을 최종 확정합니다.
    모든 방문 장소의 일차·시간·장소명·위도·경도·설명을 포함해야 합니다.
    일정이 완전히 확정되기 전에는 호출하지 마세요.
    """
    return f"여행 일정 {len(items)}개 장소가 확정되었습니다."
