<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->where('is_active', true);

        $filter = $request->get('filter', 'upcoming');

        $today = Carbon::today();

        if ($filter === 'upcoming') {
            $query->where('start_date', '>=', $today);
        } elseif ($filter === 'today') {
            $query->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today);
        } elseif ($filter === 'past') {
            $query->where('end_date', '<', $today);
        }

        $events = $query->orderBy('start_date')->get();

        return $this->success([
            'events' => EventResource::collection($events),
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        return $this->success([
            'event' => new EventResource($event),
        ]);
    }
}
