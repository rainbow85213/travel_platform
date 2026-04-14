<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\TourCastException;
use App\Services\TourCastService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlaceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $places = Place::query()
            ->when($request->search,   fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->when($request->city,     fn ($q, $v) => $q->where('city', $v))
            ->when($request->country,  fn ($q, $v) => $q->where('country', $v))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->orderByDesc('rating')
            ->paginate(20);

        if ($places->isEmpty()) {
            $searchTerm = (string) ($request->search ?? $request->city ?? '');

            if (!empty($searchTerm)) {
                try {
                    $service = app(TourCastService::class);
                    $raw     = $service->searchTouristSpots($searchTerm, 20);
                    $items   = $raw['items'] ?? [];

                    if (!empty($items)) {
                        return $this->success(
                            collect($items)->map(fn (array $t) => $this->mapTourCastItem($t))
                        );
                    }
                } catch (TourCastException $e) {
                    Log::warning('TourCast fallback 실패, 빈 결과 반환', ['error' => $e->getMessage()]);
                }
            }
        }

        return $this->success($places);
    }

    public function show(Place $place): JsonResponse
    {
        return $this->success($place);
    }

    /**
     * TourCast 응답 항목을 Place 응답 형식으로 변환
     *
     * @param  array<string, mixed> $t
     * @return array<string, mixed>
     */
    private function mapTourCastItem(array $t): array
    {
        return [
            'id'          => $t['id'] ?? null,
            'name'        => $t['title'] ?? '',
            'description' => $t['overview'] ?? null,
            'latitude'    => $t['mapY'] ?? null,   // TourCast: mapY = 위도
            'longitude'   => $t['mapX'] ?? null,   // TourCast: mapX = 경도
            'city'        => null,
            'country'     => '한국',
            'category'    => 'attraction',
            'rating'      => null,
            'address'     => $t['address'] ?? null,
            'source'      => 'tourcast',
        ];
    }
}
