<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
