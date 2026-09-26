<?php

namespace Database\Factories;

use App\Models\AuthOtpChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuthOtpChallenge>
 */
class AuthOtpChallengeFactory extends Factory
{
    protected $model = AuthOtpChallenge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'login',
            'user_id' => User::factory(),
            'target_phone' => fake()->numerify('62812########'),
            'payload' => ['device_name' => 'Test Device'],
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => null,
        ];
    }
}
