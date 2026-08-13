<?php

it('publishes complete OpenAPI documentation for the mobile API', function () {
    $response = $this->getJson('/docs/api.json');

    $response->assertSuccessful()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', '126 Club Mobile API')
        ->assertJsonPath('components.securitySchemes.http.type', 'http')
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
        ->assertJsonPath('paths./v1/login.post.security', [])
        ->assertJsonStructure([
            'paths' => [
                '/v1/register' => ['post'],
                '/v1/login' => ['post'],
                '/v1/me' => ['get'],
                '/v1/bookings' => ['get', 'post'],
                '/v1/events' => ['get'],
                '/v1/promos' => ['get'],
                '/v1/rewards' => ['get'],
                '/v1/song-requests' => ['get', 'post'],
                '/v1/payment-info' => ['get'],
            ],
        ]);

    expect($response->json('paths'))->toHaveCount(31);
});
