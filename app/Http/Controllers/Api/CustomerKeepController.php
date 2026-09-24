<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerKeepResource;
use App\Models\CustomerKeep;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerKeepController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->customerUser) {
            return $this->success(['bottles' => []]);
        }

        $keeps = CustomerKeep::where('customer_user_id', $user->customerUser->id)
            ->latest('stored_at')
            ->get();

        return $this->success([
            'bottles' => CustomerKeepResource::collection($keeps),
        ]);
    }

    public function show(CustomerKeep $customerKeep): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($customerKeep->customer_user_id !== $user->customerUser?->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success([
            'bottle' => new CustomerKeepResource($customerKeep),
        ]);
    }
}
