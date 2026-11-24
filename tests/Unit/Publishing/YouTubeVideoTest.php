<?php

namespace Tests\Unit\Publishing;

use App\Support\Publishing\YouTubeVideo;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YouTubeVideoTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptedUrlProvider(): array
    {
        return [
            'watch www' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch bare' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch with extras' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=12s'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'privacy embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('acceptedUrlProvider')]
    public function test_it_accepts_supported_youtube_url_forms(string $url): void
    {
        $video = YouTubeVideo::tryFrom($url);

        $this->assertNotNull($video);
        $this->assertSame('dQw4w9WgXcQ', $video->videoId());
        $this->assertSame($url, $video->sourceUrl());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $video->embedUrl());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejectedUrlProvider(): array
    {
        return [
            'blank' => ['   '],
            'vimeo' => ['https://vimeo.com/123456789'],
            'example' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
            'lookalike subdomain' => ['https://youtube.example.com/watch?v=dQw4w9WgXcQ'],
            'lookalike host' => ['https://evil-youtube.com/watch?v=dQw4w9WgXcQ'],
            'suffix host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
            'iframe html' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
            'credentials' => ['https://user:pass@www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'missing id' => ['https://www.youtube.com/watch'],
            'short id' => ['https://youtu.be/short'],
        ];
    }

    #[DataProvider('rejectedUrlProvider')]
    public function test_it_rejects_non_youtube_and_unsafe_urls(string $url): void
    {
        $this->assertNull(YouTubeVideo::tryFrom($url));
    }

    public function test_public_payload_is_null_when_url_is_absent(): void
    {
        $this->assertNull(YouTubeVideo::publicPayload(null));
        $this->assertNull(YouTubeVideo::publicPayload(''));
    }

    public function test_embed_url_is_derived_and_does_not_echo_the_source(): void
    {
        $video = YouTubeVideo::tryFrom('https://youtu.be/dQw4w9WgXcQ');

        $this->assertNotNull($video);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $video->sourceUrl());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $video->embedUrl());
        $this->assertStringNotContainsString('youtu.be', $video->embedUrl());
    }
}
