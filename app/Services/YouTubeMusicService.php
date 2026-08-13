<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeMusicService
{
    private const ENDPOINT = 'https://music.youtube.com/youtubei/v1/search';

    private const API_KEY = 'AIzaSyC9XL3ZjWddXya6X74dJoCTL-NKNELL6As';

    /**
     * Search params for filtering by type.
     *
     * @var array<string, string>
     */
    private const FILTER_PARAMS = [
        'songs' => 'EgWKAQIIAWoKEAkQAxAEEAoQ',
    ];

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function search(string $query, string $filter = 'songs'): array
    {
        try {
            $body = [
                'context' => [
                    'client' => [
                        'clientName' => 'WEB_REMIX',
                        'clientVersion' => '1.20210614.1.0',
                        'hl' => 'en',
                    ],
                ],
                'query' => $query,
            ];

            if (isset(self::FILTER_PARAMS[$filter])) {
                $body['params'] = self::FILTER_PARAMS[$filter];
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Content-Type' => 'application/json',
                    'X-Goog-AuthUser' => '0',
                    'x-origin' => 'https://music.youtube.com',
                    'Referer' => 'https://music.youtube.com/',
                ])
                ->post(self::ENDPOINT.'?key='.self::API_KEY.'&prettyPrint=false', $body);

            if ($response->failed()) {
                Log::error('YouTube Music API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('YouTube Music API request gagal: '.$response->status());
            }

            return $this->parseSearchResults($response->json());
        } catch (ConnectionException $e) {
            Log::error('YouTube Music API connection error', ['message' => $e->getMessage()]);

            throw new \RuntimeException('Tidak dapat terhubung ke YouTube Music API.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<int, array<string, mixed>>
     */
    private function parseSearchResults(?array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $contents = data_get($data, 'contents.tabbedSearchResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents', []);

        $results = [];

        foreach ($contents as $section) {
            foreach ($this->parseSection($section) as $track) {
                $results[$track['video_id']] = $track;
            }
        }

        return array_values($results);
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<int, array<string, mixed>>
     */
    private function parseSection(array $section): array
    {
        $tracks = [];

        $cardTrack = $this->parseCardTrack(data_get($section, 'musicCardShelfRenderer', []));

        if ($cardTrack !== null) {
            $tracks[] = $cardTrack;
        }

        $items = array_merge(
            data_get($section, 'musicShelfRenderer.contents', []),
            data_get($section, 'musicCardShelfRenderer.contents', []),
        );

        foreach ($items as $item) {
            $parsed = $this->parseTrack($item);

            if ($parsed !== null) {
                $tracks[] = $parsed;
            }
        }

        return $tracks;
    }

    /**
     * @param  array<string, mixed>  $renderer
     * @return array<string, mixed>|null
     */
    private function parseCardTrack(array $renderer): ?array
    {
        if (empty($renderer)) {
            return null;
        }

        $videoId = data_get($renderer, 'title.runs.0.navigationEndpoint.watchEndpoint.videoId');

        if (empty($videoId)) {
            return null;
        }

        $artist = collect(data_get($renderer, 'subtitle.runs', []))
            ->filter(fn (array $run): bool => data_get($run, 'navigationEndpoint.browseEndpoint.browseEndpointContextSupportedConfigs.browseEndpointContextMusicConfig.pageType') === 'MUSIC_PAGE_TYPE_ARTIST')
            ->pluck('text')
            ->implode(', ');

        $thumbnails = data_get($renderer, 'thumbnail.musicThumbnailRenderer.thumbnail.thumbnails', []);
        $thumbnail = collect($thumbnails)->sortByDesc('width')->first();

        return [
            'video_id' => $videoId,
            'title' => data_get($renderer, 'title.runs.0.text'),
            'artist' => $artist,
            'thumbnail' => data_get($thumbnail, 'url'),
            'url' => 'https://music.youtube.com/watch?v='.$videoId,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function parseTrack(array $item): ?array
    {
        $renderer = data_get($item, 'musicResponsiveListItemRenderer');

        if (empty($renderer)) {
            return null;
        }

        $videoId = data_get($renderer, 'playlistItemData.videoId')
            ?? data_get($renderer, 'overlay.musicItemThumbnailOverlayRenderer.content.musicPlayButtonRenderer.playNavigationEndpoint.watchEndpoint.videoId')
            ?? data_get($renderer, 'flexColumns.0.musicResponsiveListItemFlexColumnRenderer.text.runs.0.navigationEndpoint.watchEndpoint.videoId');

        if (empty($videoId)) {
            return null;
        }

        $title = data_get($renderer, 'flexColumns.0.musicResponsiveListItemFlexColumnRenderer.text.runs.0.text');

        $artistRuns = data_get($renderer, 'flexColumns.1.musicResponsiveListItemFlexColumnRenderer.text.runs', []);
        $artist = collect($artistRuns)
            ->filter(fn (array $run): bool => isset($run['navigationEndpoint']['browseEndpoint']))
            ->pluck('text')
            ->implode(', ');

        if (empty($artist)) {
            $artist = data_get($artistRuns, '0.text');
        }

        $thumbnails = data_get($renderer, 'thumbnail.musicThumbnailRenderer.thumbnail.thumbnails', []);
        $thumbnail = collect($thumbnails)->sortByDesc('width')->first();

        return [
            'video_id' => $videoId,
            'title' => $title,
            'artist' => $artist,
            'thumbnail' => data_get($thumbnail, 'url'),
            'url' => 'https://music.youtube.com/watch?v='.$videoId,
        ];
    }
}
