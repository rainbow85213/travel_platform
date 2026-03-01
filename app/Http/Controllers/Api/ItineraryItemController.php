<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        return $this->success($itinerary->items()->with('place')->get());
    }

    public function store(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'place_id'         => ['required', 'exists:places,id'],
            'day_number'       => ['required', 'integer', 'min:1'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
            'visited_at'       => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'notes'            => ['nullable', 'string'],
        ]);

        $item = $itinerary->items()->create($data);

        return $this->created($item->load('place'), '아이템이 추가되었습니다.');
    }

    public function update(Request $request, Itinerary $itinerary, ItineraryItem $item): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id || $item->itinerary_id !== $itinerary->id) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'place_id'         => ['sometimes', 'exists:places,id'],
            'day_number'       => ['sometimes', 'integer', 'min:1'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
            'visited_at'       => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'notes'            => ['nullable', 'string'],
        ]);

        $item->update($data);

        return $this->success($item->fresh()->load('place'), '아이템이 수정되었습니다.');
    }

    public function destroy(Request $request, Itinerary $itinerary, ItineraryItem $item): JsonResponse
    {
        if ($itinerary->user_id !== $request->user()->id || $item->itinerary_id !== $itinerary->id) {
            return $this->forbidden();
        }

        $item->delete();

        return $this->success(message: '아이템이 삭제되었습니다.');
    }
}
