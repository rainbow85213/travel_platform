<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\Place;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItineraryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $itineraries = $request->user()
            ->itineraries()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('start_date')
            ->get();

        return $this->success($itineraries);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'status'      => ['sometimes', 'in:draft,published,archived'],
        ]);

        $itinerary = $request->user()->itineraries()->create($data);

        return $this->created($itinerary, '일정이 생성되었습니다.');
    }

    public function show(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        return $this->success($itinerary->load('items.place'));
    }

    public function update(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['sometimes', 'date', 'after_or_equal:start_date'],
            'status'      => ['sometimes', 'in:draft,published,archived'],
        ]);

        $itinerary->update($data);

        return $this->success($itinerary->fresh(), '일정이 수정되었습니다.');
    }

    public function destroy(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $itinerary->delete();

        return $this->success(message: '일정이 삭제되었습니다.');
    }

    /**
     * 챗봇 확정 일정을 itineraries + itinerary_items 테이블에 저장
     *
     * Request body:
     * {
     *   "title":      "제주도 1박 2일",          // optional
     *   "start_date": "2026-04-01",             // optional, default: today
     *   "itinerary": [
     *     { "day": 1, "time": "09:00", "place": "성산일출봉",
     *       "latitude": 33.4589, "longitude": 126.9425, "description": "..." },
     *     ...
     *   ]
     * }
     */
    public function saveFromChatbot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                    => ['sometimes', 'string', 'max:255'],
            'start_date'               => ['sometimes', 'date'],
            'itinerary'                => ['required', 'array', 'min:1'],
            'itinerary.*.day'          => ['required', 'integer', 'min:1'],
            'itinerary.*.time'         => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'itinerary.*.place'        => ['required', 'string', 'max:255'],
            'itinerary.*.latitude'     => ['required', 'numeric', 'between:-90,90'],
            'itinerary.*.longitude'    => ['required', 'numeric', 'between:-180,180'],
            'itinerary.*.description'  => ['sometimes', 'nullable', 'string'],
        ]);

        $startDate = Carbon::parse($data['start_date'] ?? today());
        $maxDay    = max(array_column($data['itinerary'], 'day'));
        $endDate   = $startDate->copy()->addDays($maxDay - 1);

        $itinerary = DB::transaction(function () use ($request, $data, $startDate, $endDate) {
            // 1. itineraries 레코드 생성
            $itinerary = $request->user()->itineraries()->create([
                'title'       => $data['title'] ?? ('챗봇 일정 ' . $startDate->format('Y-m-d')),
                'description' => '챗봇이 확정한 여행 일정입니다.',
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'status'      => 'draft',
            ]);

            // 같은 day 내 sort_order 계산용 카운터
            $sortCounters = [];

            // 2. 각 항목을 place 매칭/생성 후 itinerary_items 삽입
            foreach ($data['itinerary'] as $item) {
                $day = $item['day'];

                // Place: 이름 일치 우선, 없으면 신규 생성
                $place = Place::whereRaw('LOWER(name) = ?', [mb_strtolower($item['place'])])
                    ->first();

                if (! $place) {
                    $place = Place::create([
                        'name'        => $item['place'],
                        'description' => $item['description'] ?? null,
                        'latitude'    => $item['latitude'],
                        'longitude'   => $item['longitude'],
                        'city'        => null,
                        'country'     => null,
                        'category'    => 'attraction',
                    ]);
                }

                // visited_at = start_date + (day-1)일 + 시간
                $visitedAt = $startDate->copy()
                    ->addDays($day - 1)
                    ->setTimeFromTimeString($item['time']);

                $sortCounters[$day] = ($sortCounters[$day] ?? 0);

                $itinerary->items()->create([
                    'place_id'   => $place->id,
                    'day_number' => $day,
                    'sort_order' => $sortCounters[$day]++,
                    'visited_at' => $visitedAt,
                    'notes'      => $item['description'] ?? null,
                ]);
            }

            return $itinerary;
        });

        return $this->created(
            $itinerary->load('items.place'),
            '챗봇 일정이 저장되었습니다.'
        );
    }
}
