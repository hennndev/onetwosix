<?php

use App\Models\Event;

it('lists active upcoming events', function () {
    Event::create([
        'name' => 'DJ Night',
        'slug' => 'dj-night',
        'start_date' => now()->addDays(3),
        'end_date' => now()->addDays(3),
        'start_time' => '21:00',
        'end_time' => '02:00',
        'is_active' => true,
    ]);

    Event::create([
        'name' => 'Inactive Event',
        'slug' => 'inactive-event',
        'start_date' => now()->addDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/v1/events');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.name', 'DJ Night');
});

it('shows event detail', function () {
    $event = Event::create([
        'name' => 'Live Session',
        'slug' => 'live-session',
        'description' => 'Amazing live music night',
        'start_date' => now()->addDays(2),
        'end_date' => now()->addDays(2),
        'start_time' => '20:00',
        'end_time' => '01:00',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/v1/events/{$event->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'event' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'image',
                    'start_date',
                    'end_date',
                    'is_active',
                    'is_upcoming',
                ],
            ],
        ]);
});

it('filters past events', function () {
    Event::create([
        'name' => 'Past Event',
        'slug' => 'past-event',
        'start_date' => now()->subDays(5),
        'end_date' => now()->subDays(4),
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/events?filter=past');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.events');
});
