<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Promo::query()->where('is_active', true);

        $filter = $request->get('filter', 'upcoming');

        $today = Carbon::today();

        if ($filter === 'upcoming') {
            $query->where('start_date', '>=', $today);
        } elseif ($filter === 'today') {
            $query->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today);
        } elseif ($filter === 'past') {
            $query->where('end_date', '<', $today);
        } elseif ($filter === 'all') {
            // No additional filter
        }

        $promos = $query->orderBy('start_date')->get();

        return $this->success([
            'promos' => PromoResource::collection($promos),
        ]);
    }

    public function show(Promo $promo): JsonResponse
    {
        return $this->success([
            'promo' => new PromoResource($promo),
        ]);
    }
}
