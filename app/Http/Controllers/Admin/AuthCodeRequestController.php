<?php

namespace App\Http\Controllers\Admin;

use App\Events\AuthCodeRequestUpdated;
use App\Http\Controllers\Controller;
use App\Models\AuthCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthCodeRequestController extends Controller
{
    public function show(AuthCodeRequest $authCodeRequest): View
    {
        return view('admin.auth-code-requests.show', [
            'request' => $authCodeRequest,
        ]);
    }

    public function update(Request $request, AuthCodeRequest $authCodeRequest): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'manager_note' => ['nullable', 'string', 'max:500'],
        ]);

        $authCodeRequest->update([
            'status' => $request->status,
            'manager_note' => $request->manager_note,
            'approved_at' => now(),
        ]);

        // Broadcast event
        broadcast(new AuthCodeRequestUpdated($authCodeRequest));

        return back()->with('success', 'Permintaan Auth Code berhasil diperbarui.');
    }
}
