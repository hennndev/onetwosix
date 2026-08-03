<?php

namespace App\Http\Controllers;

use App\Models\DisplayMessageRequest;
use App\Models\User;
use Illuminate\Http\Request;

class DisplayMessageRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = DisplayMessageRequest::with(['customer.profile', 'customer.customerUser']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
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

        $messages = $query->latest()->get();

        $totalMessages = DisplayMessageRequest::count();
        $pendingMessages = DisplayMessageRequest::where('status', 'pending')->count();
        $displayedMessages = DisplayMessageRequest::where('status', 'displayed')->count();

        $customers = User::whereHas('customerUser')->with('profile')->orderBy('name')->get();

        return view('display-messages.index', compact(
            'messages',
            'totalMessages',
            'pendingMessages',
            'displayedMessages',
            'customers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'message' => 'required|string|max:500',
            'tip' => 'nullable|integer|min:0',
            'status' => 'required|in:pending,displayed,completed,rejected,cancelled',
        ]);

        try {
            if ($validated['status'] === 'displayed') {
                $activeMsg = DisplayMessageRequest::where('status', 'displayed')->first();
                if ($activeMsg) {
                    $formattedId = 'MSG-'.str_pad($activeMsg->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal menampilkan pesan: Masih ada pesan lain yang sedang aktif ditampilkan di layar LED ({$formattedId}). Silakan matikan atau selesaikan pesan aktif tersebut terlebih dahulu.",
                    ])->withInput();
                }
            }

            DisplayMessageRequest::create($validated);

            return redirect()->route('admin.display-messages.index')
                ->with('success', 'Message request berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan message request: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, DisplayMessageRequest $displayMessage)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'message' => 'required|string|max:500',
            'tip' => 'nullable|integer|min:0',
            'status' => 'required|in:pending,displayed,completed,rejected,cancelled',
        ]);

        try {
            if ($validated['status'] === 'displayed') {
                $activeMsg = DisplayMessageRequest::where('status', 'displayed')
                    ->where('id', '!=', $displayMessage->id)
                    ->first();

                if ($activeMsg) {
                    $formattedId = 'MSG-'.str_pad($activeMsg->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal menampilkan pesan: Masih ada pesan lain yang sedang aktif ditampilkan di layar LED ({$formattedId}). Silakan matikan atau selesaikan pesan aktif tersebut terlebih dahulu.",
                    ])->withInput();
                }
            }

            $displayMessage->update($validated);

            return redirect()->route('admin.display-messages.index')
                ->with('success', 'Message request berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate message request: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(DisplayMessageRequest $displayMessage)
    {
        try {
            $displayMessage->delete();

            return redirect()->route('admin.display-messages.index')
                ->with('success', 'Message request berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus message request: '.$e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, DisplayMessageRequest $displayMessage)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,displayed,completed,rejected,cancelled',
        ]);

        try {
            if ($validated['status'] === 'displayed') {
                $activeMsg = DisplayMessageRequest::where('status', 'displayed')
                    ->where('id', '!=', $displayMessage->id)
                    ->first();

                if ($activeMsg) {
                    $formattedId = 'MSG-'.str_pad($activeMsg->id, 4, '0', STR_PAD_LEFT);

                    return back()->withErrors([
                        'error' => "Gagal menampilkan pesan: Masih ada pesan lain yang sedang aktif ditampilkan di layar LED ({$formattedId}). Silakan matikan atau selesaikan pesan aktif tersebut terlebih dahulu.",
                    ]);
                }
            }

            $displayMessage->update(['status' => $validated['status']]);

            $statusMessages = [
                'displayed' => 'Message berhasil ditampilkan',
                'completed' => 'Penayangan message selesai',
                'rejected' => 'Message berhasil ditolak',
                'cancelled' => 'Message berhasil dibatalkan',
                'pending' => 'Message dikembalikan ke pending',
            ];

            $message = $statusMessages[$validated['status']] ?? 'Status message berhasil diupdate';

            return redirect()->route('admin.display-messages.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate status: '.$e->getMessage()]);
        }
    }
}
