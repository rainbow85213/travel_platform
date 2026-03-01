<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->success($places);
    }

    public function show(Place $place): JsonResponse
    {
        return $this->success($place);
    }
}
