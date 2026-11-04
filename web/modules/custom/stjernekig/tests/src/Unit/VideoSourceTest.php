<?php

declare(strict_types=1);

namespace Drupal\Tests\stjernekig\Unit;

use Drupal\stjernekig\Media\VideoSource;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\stjernekig\Media\VideoSource
 * @group stjernekig
 */
final class VideoSourceTest extends UnitTestCase {

  /**
   * @covers ::fromUrl
   * @covers ::embedUrl
   * @covers ::watchUrl
   * @dataProvider urlProvider
   */
  public function testClassification(string $url, string $provider, ?string $embed, string $watch, ?string $mime): void {
    $source = VideoSource::fromUrl($url);
    $this->assertSame($provider, $source->provider);
    $this->assertSame($embed, $source->embedUrl());
    $this->assertSame($watch, $source->watchUrl());
    $this->assertSame($mime, $source->mimeType);
    $this->assertSame($provider !== VideoSource::FILE && $provider !== VideoSource::UNKNOWN, $source->isEmbed());
  }

  /**
   * Video URLs as the parser stores them, plus a few it might meet.
   *
   * @return array<string, array{string, string, ?string, string, ?string}>
   *   URL, provider, embed URL, watch URL, MIME type.
   */
  public static function urlProvider(): array {
    return [
      'youtube nocookie embed' => [
        'https://www.youtube-nocookie.com/embed/DzrCEm1ZBRY',
        VideoSource::YOUTUBE,
        'https://www.youtube-nocookie.com/embed/DzrCEm1ZBRY?rel=0&modestbranding=1',
        'https://www.youtube.com/watch?v=DzrCEm1ZBRY',
        NULL,
      ],
      'youtube watch' => [
        'https://www.youtube.com/watch?v=DzrCEm1ZBRY',
        VideoSource::YOUTUBE,
        'https://www.youtube-nocookie.com/embed/DzrCEm1ZBRY?rel=0&modestbranding=1',
        'https://www.youtube.com/watch?v=DzrCEm1ZBRY',
        NULL,
      ],
      'youtu.be short link' => [
        'https://youtu.be/DzrCEm1ZBRY',
        VideoSource::YOUTUBE,
        'https://www.youtube-nocookie.com/embed/DzrCEm1ZBRY?rel=0&modestbranding=1',
        'https://www.youtube.com/watch?v=DzrCEm1ZBRY',
        NULL,
      ],
      'vimeo player' => [
        'https://player.vimeo.com/video/123456',
        VideoSource::VIMEO,
        'https://player.vimeo.com/video/123456?dnt=1',
        'https://vimeo.com/123456',
        NULL,
      ],
      'nasa mp4' => [
        'https://assets.science.nasa.gov/content/dam/science/cds/apod/apod/2026/october/occultation.mp4',
        VideoSource::FILE,
        NULL,
        'https://assets.science.nasa.gov/content/dam/science/cds/apod/apod/2026/october/occultation.mp4',
        'video/mp4',
      ],
      'webm with query' => [
        'https://example.org/clip.webm?x=1',
        VideoSource::FILE,
        NULL,
        'https://example.org/clip.webm?x=1',
        'video/webm',
      ],
      'unknown player page' => [
        'https://example.org/player/42',
        VideoSource::UNKNOWN,
        NULL,
        'https://example.org/player/42',
        NULL,
      ],
    ];
  }

  /**
   * @covers ::embedUrl
   */
  public function testAutoplayIsAppendedForEmbeds(): void {
    $this->assertStringEndsWith('&autoplay=1', (string) VideoSource::fromUrl('https://youtu.be/DzrCEm1ZBRY')->embedUrl(TRUE));
    $this->assertStringEndsWith('&autoplay=1', (string) VideoSource::fromUrl('https://vimeo.com/5')->embedUrl(TRUE));
  }

  /**
   * @covers ::isStorableMime
   * @covers ::extension
   */
  public function testStorableTypes(): void {
    $this->assertTrue(VideoSource::isStorableMime('video/mp4'));
    $this->assertTrue(VideoSource::isStorableMime('VIDEO/WEBM '));
    $this->assertFalse(VideoSource::isStorableMime('text/html'));
    $this->assertSame('mp4', VideoSource::fromUrl('https://example.org/a.m4v')->extension());
    $this->assertNull(VideoSource::fromUrl('https://youtu.be/DzrCEm1ZBRY')->extension());
  }

}
