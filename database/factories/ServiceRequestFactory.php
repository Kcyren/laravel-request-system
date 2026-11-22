<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'requester_name' => fn (array $attributes) => User::find($attributes['user_id'])->name,
            'requester_email' => fn (array $attributes) => User::find($attributes['user_id'])->email,
            'item_name' => fake()->randomElement(['HDMI Adapter', 'Projector', 'Extension Cord', 'Webcam']),
            'quantity' => fake()->numberBetween(1, 5),
            'purpose' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
