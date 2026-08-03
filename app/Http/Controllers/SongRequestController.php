<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Models\SongRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SongRequestController extends Controller
{
    public function searchApi(Request $request)
    {
        $term = trim($request->input('q', ''));
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        try {
            $response = Http::timeout(5)->get('https://itunes.apple.com/search', [
                'term' => $term,
                'entity' => 'song',
                'limit' => 10,
            ]);

            if ($response->successful()) {
                $results = collect($response->json('results', []))->map(function ($item) {
                    return [
                        'song_title' => $item['trackName'] ?? '',
                        'artist' => $item['artistName'] ?? '',
                        'cover_image' => isset($item['artworkUrl100']) ? str_replace('100x100bb', '300x300bb', $item['artworkUrl100']) : null,
                        'preview_url' => $item['previewUrl'] ?? null,
                        'album' => $item['collectionName'] ?? '',
                    ];
                })->filter(fn ($item) => ! empty($item['song_title']))->values();

                return response()->json($results);
            }
        } catch (\Exception $e) {
            // Return empty array gracefully on timeout or connectivity issues
        }

        return response()->json([]);
    }

    public function index(Request $request)
    {
        $query = SongRequest::with(['customerUser.user.profile']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('song_title', 'like', "%{$search}%")
                    ->orWhere('artist', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('customerUser.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('profile', function ($profileQuery) use ($search) {
                                $profileQuery->where('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $songRequests = $query->latest()->get();

        $totalRequests = SongRequest::count();
        $pendingRequests = SongRequest::where('status', 'pending')->count();
        $playedRequests = SongRequest::where('status', 'played')->count();

        $customerUsers = CustomerUser::with('user.profile')->whereHas('user')->get();

        return view('song-requests.index', compact(
            'songRequests',
            'totalRequests',
            'pendingRequests',
            'playedRequests',
            'customerUsers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|exists:customer_users,id',
            'song_title' => 'required|string|max:255',
            'artist' => 'required|string|max:255',
            'cover_image' => 'nullable|url|max:500',
            'preview_url' => 'nullable|url|max:500',
            'tip' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,played,completed,rejected',
        ]);

        try {
            if ($validated['status'] === 'played') {
                $activeSong = SongRequest::where('status', 'played')->first();
                if ($activeSong) {
                    $formattedId = 'SONG-'.str_pad($activeSong->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal memutar lagu: Masih ada lagu lain yang sedang aktif diputar ({$formattedId} - {$activeSong->song_title}). Silakan selesaikan lagu aktif tersebut terlebih dahulu.",
                    ])->withInput();
                }
            }

            SongRequest::create($validated);

            return redirect()->route('admin.song-requests.index')
                ->with('success', 'Song request berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan song request: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, SongRequest $songRequest)
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|exists:customer_users,id',
            'song_title' => 'required|string|max:255',
            'artist' => 'required|string|max:255',
            'cover_image' => 'nullable|url|max:500',
            'preview_url' => 'nullable|url|max:500',
            'tip' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,played,completed,rejected',
        ]);

        try {
            if ($validated['status'] === 'played') {
                $activeSong = SongRequest::where('status', 'played')
                    ->where('id', '!=', $songRequest->id)
                    ->first();

                if ($activeSong) {
                    $formattedId = 'SONG-'.str_pad($activeSong->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal memutar lagu: Masih ada lagu lain yang sedang aktif diputar ({$formattedId} - {$activeSong->song_title}). Silakan selesaikan lagu aktif tersebut terlebih dahulu.",
                    ])->withInput();
                }
            }

            $songRequest->update($validated);

            return redirect()->route('admin.song-requests.index')
                ->with('success', 'Song request berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate song request: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(SongRequest $songRequest)
    {
        try {
            $songRequest->delete();

            return redirect()->route('admin.song-requests.index')
                ->with('success', 'Song request berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus song request: '.$e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, SongRequest $songRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,played,completed,rejected',
        ]);

        try {
            if ($validated['status'] === 'played') {
                $activeSong = SongRequest::where('status', 'played')
                    ->where('id', '!=', $songRequest->id)
                    ->first();

                if ($activeSong) {
                    $formattedId = 'SONG-'.str_pad($activeSong->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal memutar lagu: Masih ada lagu lain yang sedang aktif diputar ({$formattedId} - {$activeSong->song_title}). Silakan selesaikan lagu aktif tersebut terlebih dahulu.",
                    ]);
                }
            }

            $songRequest->update(['status' => $validated['status']]);

            $statusMessages = [
                'played' => 'Lagu berhasil diputar di DJ Booth',
                'completed' => 'Penayangan lagu selesai',
                'rejected' => 'Song request berhasil ditolak',
                'pending' => 'Song dikembalikan ke pending',
            ];

            $message = $statusMessages[$validated['status']] ?? 'Status song request berhasil diupdate';

            return redirect()->route('admin.song-requests.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate status: '.$e->getMessage()]);
        }
    }
}
