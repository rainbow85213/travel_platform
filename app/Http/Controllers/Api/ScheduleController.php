<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TourCastException;
use App\Services\TourCastService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TourCastService $tourCast) {}

    /**
     * GET /api/schedule/map
     * TourCast getMapItems 프록시
     */
    public function map(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ['userId' => 'user_' . $user->id];

        if ($request->filled('date')) {
            $query['date'] = $request->input('date');
        }

        if ($request->filled('filters')) {
            $query['filters'] = $request->input('filters');
        }

        try {
            $data = $this->tourCast->proxyScheduleGet('map', $query);

            return $this->success($data, '지도 데이터 조회 성공');
        } catch (TourCastException $e) {
            return $this->failure('지도 데이터 조회에 실패했습니다.', 500);
        }
    }

    /**
     * GET /api/schedule/route
     * TourCast getRoute 프록시
     */
    public function route(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ['userId' => 'user_' . $user->id];

        if ($request->filled('date')) {
            $query['date'] = $request->input('date');
        }

        try {
            $data = $this->tourCast->proxyScheduleGet('route', $query);

            return $this->success($data, '경로 조회 성공');
        } catch (TourCastException $e) {
            return $this->failure('경로 조회에 실패했습니다.', 500);
        }
    }

    /**
     * GET /api/schedule/heatmap
     * TourCast getHeatmap 프록시
     */
    public function heatmap(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $this->tourCast->proxyScheduleGet('heatmap', [
                'userId' => 'user_' . $user->id,
            ]);

            return $this->success($data, '히트맵 조회 성공');
        } catch (TourCastException $e) {
            return $this->failure('히트맵 조회에 실패했습니다.', 500);
        }
    }

    /**
     * GET /api/schedule/list
     * TourCast listTravelPlans 프록시
     */
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'page'  => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $query = ['userId' => 'user_' . $user->id];

        if ($request->filled('page')) {
            $query['page'] = $request->integer('page');
        }

        if ($request->filled('limit')) {
            $query['limit'] = $request->integer('limit');
        }

        try {
            $data = $this->tourCast->proxyScheduleGet('list', $query);

            return $this->success($data, '일정 목록 조회 성공');
        } catch (TourCastException $e) {
            return $this->failure('일정 목록 조회에 실패했습니다.', 500);
        }
    }

    /**
     * POST /api/schedule
     * TourCast createTravelPlan 프록시
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'date'           => ['required', 'date_format:Y-m-d'],
            'sourceText'     => ['sometimes', 'string'],
            'items'          => ['sometimes', 'array'],
            'items.*.order'  => ['required_with:items', 'integer'],
        ]);

        $user = $request->user();

        $body = array_merge(
            $request->only(['title', 'date', 'sourceText', 'items']),
            ['userId' => 'user_' . $user->id]
        );

        // items.time 이 HH:MM 형식이 아닐 경우 정규화 (AI 자유형식 텍스트 방어)
        if (isset($body['items']) && is_array($body['items'])) {
            $body['items'] = array_map(
                fn (array $item) => array_merge($item, ['time' => $this->normalizeTime($item['time'] ?? '09:00')]),
                $body['items']
            );
        }

        try {
            $data = $this->tourCast->proxySchedulePost('', $body);

            return $this->created($data, '일정이 생성되었습니다.');
        } catch (TourCastException $e) {
            return $this->failure('일정 생성에 실패했습니다.', 500);
        }
    }

    /**
     * time 값을 TourCast가 요구하는 HH:MM 형식으로 정규화한다.
     * AI가 "점심", "저녁" 등 자유형식 텍스트를 반환할 경우 대응 시간으로 변환.
     */
    private function normalizeTime(string $time): string
    {
        $time = trim($time);

        // 이미 HH:MM 형식
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        // H:MM → HH:MM (한 자리 시)
        if (preg_match('/^(\d):(\d{2})$/', $time, $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        // 한국어 시간 표현 매핑
        $koMap = [
            '새벽' => '04:00',
            '아침' => '08:00',
            '오전' => '09:00',
            '점심' => '12:00',
            '오후' => '14:00',
            '저녁' => '18:00',
            '밤'   => '20:00',
            '숙박' => '21:00',
            '야간' => '21:00',
        ];

        foreach ($koMap as $ko => $hhmm) {
            if (str_contains($time, $ko)) {
                return $hhmm;
            }
        }

        return '09:00'; // 매핑 없으면 기본값
    }

    /**
     * PATCH /api/schedule/item/{itemId}
     * TourCast updateItemStatus 프록시
     */
    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string'],
        ]);

        try {
            $data = $this->tourCast->proxySchedulePatch("item/{$itemId}", [
                'status' => $request->input('status'),
            ]);

            return $this->success($data, '아이템 상태가 업데이트되었습니다.');
        } catch (TourCastException $e) {
            return $this->failure('아이템 상태 업데이트에 실패했습니다.', 500);
        }
    }

    /**
     * POST /api/user/device-token
     * FCM 디바이스 토큰 저장
     */
    public function deviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_token'    => ['required', 'string'],
            'device_platform' => ['required', 'string', 'in:ios,android'],
        ]);

        $request->user()->update([
            'device_token'    => $request->input('device_token'),
            'device_platform' => $request->input('device_platform'),
        ]);

        return $this->success(null, '디바이스 토큰이 저장되었습니다.');
    }
}
