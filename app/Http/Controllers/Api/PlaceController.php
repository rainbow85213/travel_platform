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
            try {
                $service = app(TourCastService::class);
                $keyword = (string) ($request->search ?? $request->city ?? $request->country ?? '');
                $filters = array_filter([
                    'category' => $request->category,
                    'city'     => $request->city,
                    'country'  => $request->country,
                ]);

                $raw   = $keyword
                    ? $service->searchTours($keyword, $filters)
                    : $service->getTours($filters);
                $items = $raw['data'] ?? (array_is_list($raw) ? $raw : []);

                return $this->success(
                    collect($items)->map(fn (array $t) => $this->mapTourCastItem($t))
                );
            } catch (TourCastException $e) {
                Log::warning('TourCast fallback 실패, 빈 결과 반환', ['error' => $e->getMessage()]);
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
            'name'        => $t['name'] ?? $t['title'] ?? '',
            'description' => $t['description'] ?? null,
            'latitude'    => $t['latitude'] ?? $t['lat'] ?? null,
            'longitude'   => $t['longitude'] ?? $t['lng'] ?? $t['lon'] ?? null,
            'city'        => $t['city'] ?? null,
            'country'     => $t['country'] ?? null,
            'category'    => $t['category'] ?? 'attraction',
            'rating'      => $t['rating'] ?? $t['score'] ?? null,
            'source'      => 'tourcast',
        ];
    }
}
