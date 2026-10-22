<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Client;

/**
 * One normalised Astronomy Picture of the Day, independent of its source.
 */
final class ApodEntry {

  public const MEDIA_IMAGE = 'image';
  public const MEDIA_VIDEO = 'video';

  /**
   * Helper.
   *
   * @param string $date
   *   Publication date, YYYY-MM-DD.
   * @param string $title
   *   Title without the "APOD: 2024 December 25 -" prefix.
   * @param string $explanationHtml
   *   The explanation as sanitised HTML (links kept, layout stripped).
   * @param string $creditLabel
   *   The label NASA used, e.g. "Image Credit & Copyright".
   * @param string $creditHtml
   *   The credit line as HTML.
   * @param string $authors
   *   Plain-text authors and editors.
   * @param string $mediaType
   *   One of the MEDIA_* constants.
   * @param string|null $imageUrl
   *   Full resolution image. For videos, a still if NASA provides one.
   * @param string|null $videoUrl
   *   Embed URL for video entries.
   * @param string $sourceUrl
   *   The entry's page at the source.
   * @param string|null $legacyUrl
   *   The historical apod.nasa.gov address, when known.
   * @param int $remoteId
   *   Post ID at the source.
   * @param string|null $modified
   *   Last modification timestamp at the source, ISO 8601.
   */
  public function __construct(
    public readonly string $date,
    public readonly string $title,
    public readonly string $explanationHtml,
    public readonly string $creditLabel,
    public readonly string $creditHtml,
    public readonly string $authors,
    public readonly string $mediaType,
    public readonly ?string $imageUrl,
    public readonly ?string $videoUrl,
    public readonly string $sourceUrl,
    public readonly ?string $legacyUrl,
    public readonly int $remoteId,
    public readonly ?string $modified = NULL,
  ) {}

  /**
   * Whether the credit line signals a copyright or license restriction.
   */
  public function isCopyrighted(): bool {
    return (bool) preg_match('/copyright|license|licence|©/i', $this->creditLabel . ' ' . $this->creditHtml);
  }

  /**
   * Credit line as plain text.
   */
  public function creditText(): string {
    return trim(html_entity_decode(strip_tags($this->creditHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }

  /**
   * Explanation as plain text, optionally truncated on a word boundary.
   */
  public function explanationText(int $maxLength = 0): string {
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($this->explanationHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
      $cut = mb_substr($text, 0, $maxLength);
      $space = mb_strrpos($cut, ' ');
      $text = rtrim($space !== FALSE ? mb_substr($cut, 0, $space) : $cut, " ,;:.") . '…';
    }
    return $text;
  }

  /**
   * Array form, used by drush output and tests.
   *
   * @return array<string, mixed>
   *   The result.
   */
  public function toArray(): array {
    return [
      'date' => $this->date,
      'title' => $this->title,
      'media_type' => $this->mediaType,
      'image_url' => $this->imageUrl,
      'video_url' => $this->videoUrl,
      'credit_label' => $this->creditLabel,
      'credit' => $this->creditText(),
      'copyrighted' => $this->isCopyrighted(),
      'authors' => $this->authors,
      'source_url' => $this->sourceUrl,
      'legacy_url' => $this->legacyUrl,
      'remote_id' => $this->remoteId,
      'modified' => $this->modified,
      'explanation' => $this->explanationText(200),
    ];
  }

}
