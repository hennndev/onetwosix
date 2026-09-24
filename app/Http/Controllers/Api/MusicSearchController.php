<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YouTubeMusicService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MusicSearchController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly YouTubeMusicService $youtubeMusic) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:200',
            'filter' => 'nullable|string|in:songs,all',
        ]);

        try {
            $filter = $request->get('filter', 'songs');
            $results = $this->youtubeMusic->search(
                query: $request->string('q')->toString(),
                filter: $filter,
            );

            return $this->success([
                'query' => $request->get('q'),
                'filter' => $filter,
                'total' => count($results),
                'results' => $results,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 503);
        }
    }
}
