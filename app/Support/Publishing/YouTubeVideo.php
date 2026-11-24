<?php

namespace App\Support\Publishing;

use Closure;

final class YouTubeVideo
{
    public const EMBED_ORIGIN = 'https://www.youtube-nocookie.com';

    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
        'www.youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    private function __construct(
        private readonly string $sourceUrl,
        private readonly string $videoId,
    ) {}

    public static function tryFrom(?string $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if ($host === '' || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $videoId = self::extractVideoId($host, (string) ($parts['path'] ?? ''), $query);

        if ($videoId === null) {
            return null;
        }

        return new self($url, $videoId);
    }

    public function videoId(): string
    {
        return $this->videoId;
    }

    public function sourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function embedUrl(): string
    {
        return self::EMBED_ORIGIN.'/embed/'.$this->videoId;
    }

    /**
     * @return array{url: string, video_id: string, embed_url: string}|null
     */
    public static function publicPayload(?string $url): ?array
    {
        $video = self::tryFrom($url);

        if ($video === null) {
            return null;
        }

        return [
            'url' => $video->sourceUrl(),
            'video_id' => $video->videoId(),
            'embed_url' => $video->embedUrl(),
        ];
    }

    public static function normalizeInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'string',
            'max:2048',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (self::tryFrom(is_string($value) ? $value : null) === null) {
                    $fail(__('The YouTube URL is invalid.'));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function extractVideoId(string $host, string $path, array $query): ?string
    {
        $bareHost = preg_replace('/^(www|m)\./', '', $host) ?? $host;
        $segments = array_values(array_filter(
            explode('/', $path),
            fn (string $segment): bool => $segment !== '',
        ));

        if ($bareHost === 'youtu.be') {
            return self::validId($segments[0] ?? null);
        }

        $fromQuery = isset($query['v']) ? self::validId($query['v']) : null;

        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $kind = $segments[0] ?? '';

        if (in_array($kind, ['embed', 'shorts', 'live', 'v'], true)) {
            return self::validId($segments[1] ?? null);
        }

        return null;
    }

    private static function validId(mixed $candidate): ?string
    {
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        if (preg_match(self::VIDEO_ID_PATTERN, $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }
}
