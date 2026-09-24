<?php

use App\Services\YouTubeMusicService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('uses the YouTube API key from Laravel configuration', function () {
    config()->set('services.youtube_music.api_key', 'youtube-test-key');

    Http::preventStrayRequests();
    Http::fake([
        'music.youtube.com/*' => Http::response([]),
    ]);

    expect(app(YouTubeMusicService::class)->search('test song'))->toBe([]);

    Http::assertSent(function ($request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'POST'
            && $request->url() === 'https://music.youtube.com/youtubei/v1/search?key=youtube-test-key&prettyPrint=false'
            && $query['key'] === 'youtube-test-key';
    });
});

it('does not send a request when the YouTube API key is missing', function () {
    config()->set('services.youtube_music.api_key');

    Http::preventStrayRequests();

    expect(fn () => app(YouTubeMusicService::class)->search('test song'))
        ->toThrow(RuntimeException::class, 'YouTube Music API key belum dikonfigurasi.');

    Http::assertNothingSent();
});

it('does not contain a hard-coded Google API key in the service source', function () {
    $source = file_get_contents(app_path('Services/YouTubeMusicService.php'));

    expect($source)
        ->not->toContain('AIza')
        ->toContain("config('services.youtube_music.api_key')");
});
