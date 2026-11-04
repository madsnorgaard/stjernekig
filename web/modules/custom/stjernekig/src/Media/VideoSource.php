<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Media;

/**
 * Where a video entry's footage comes from, and how it can be played.
 *
 * NASA has used three kinds of video on APOD: YouTube embeds, Vimeo embeds,
 * and since the move to science.nasa.gov also self-hosted MP4 files played
 * by a plain `<video>` tag. The first two can only be embedded, the third is a
 * file we can download, keep and play natively.
 */
final class VideoSource {

  public const YOUTUBE = 'youtube';
  public const VIMEO = 'vimeo';
  public const FILE = 'file';
  public const UNKNOWN = 'unknown';

  /**
   * File extensions browsers can play in a `<video>` element.
   */
  private const MIME_TYPES = [
    'mp4' => 'video/mp4',
    'm4v' => 'video/mp4',
    'webm' => 'video/webm',
    'ogv' => 'video/ogg',
    'ogg' => 'video/ogg',
    'mov' => 'video/quicktime',
  ];

  /**
   * Constructs the object.
   *
   * @param string $provider
   *   One of the class constants.
   * @param string $url
   *   The URL as stored: an embed URL for providers, the file URL for files.
   * @param string|null $id
   *   The provider's video id, when there is one.
   * @param string|null $mimeType
   *   MIME type for files, derived from the extension.
   */
  private function __construct(
    public readonly string $provider,
    public readonly string $url,
    public readonly ?string $id,
    public readonly ?string $mimeType,
  ) {}

  /**
   * Classify a stored video URL.
   */
  public static function fromUrl(string $url): self {
    $url = trim($url);
    if (preg_match('#^https?://(?:www\.)?(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $url, $m)) {
      return new self(self::YOUTUBE, $url, $m[1], NULL);
    }
    if (preg_match('#^https?://(?:player\.)?vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
      return new self(self::VIMEO, $url, $m[1], NULL);
    }
    $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (isset(self::MIME_TYPES[$extension])) {
      return new self(self::FILE, $url, NULL, self::MIME_TYPES[$extension]);
    }
    return new self(self::UNKNOWN, $url, NULL, NULL);
  }

  /**
   * Whether this is a URL of a video file we could store and play ourselves.
   */
  public static function isFileUrl(string $url): bool {
    return self::fromUrl($url)->provider === self::FILE;
  }

  /**
   * Whether the footage is played through a third-party player.
   */
  public function isEmbed(): bool {
    return $this->provider === self::YOUTUBE || $this->provider === self::VIMEO;
  }

  /**
   * Player URL for an iframe, privacy-friendly where the provider allows it.
   *
   * @param bool $autoplay
   *   Start playing as soon as the player loads (used after a click).
   */
  public function embedUrl(bool $autoplay = FALSE): ?string {
    return match ($this->provider) {
      self::YOUTUBE => 'https://www.youtube-nocookie.com/embed/' . $this->id . '?rel=0&modestbranding=1' . ($autoplay ? '&autoplay=1' : ''),
      self::VIMEO => 'https://player.vimeo.com/video/' . $this->id . '?dnt=1' . ($autoplay ? '&autoplay=1' : ''),
      default => NULL,
    };
  }

  /**
   * A page a person can open in a new tab to watch the video.
   */
  public function watchUrl(): string {
    return match ($this->provider) {
      self::YOUTUBE => 'https://www.youtube.com/watch?v=' . $this->id,
      self::VIMEO => 'https://vimeo.com/' . $this->id,
      default => $this->url,
    };
  }

  /**
   * Human-readable provider name.
   */
  public function providerLabel(): string {
    return match ($this->provider) {
      self::YOUTUBE => 'YouTube',
      self::VIMEO => 'Vimeo',
      self::FILE => 'NASA',
      default => 'the source',
    };
  }

  /**
   * File extension for a stored copy, based on the MIME type.
   */
  public function extension(): ?string {
    if ($this->mimeType === NULL) {
      return NULL;
    }
    return array_search($this->mimeType, self::MIME_TYPES, TRUE) ?: NULL;
  }

  /**
   * Whether a Content-Type header is a video type we store.
   */
  public static function isStorableMime(string $mime): bool {
    return in_array(strtolower(trim($mime)), self::MIME_TYPES, TRUE);
  }

}
