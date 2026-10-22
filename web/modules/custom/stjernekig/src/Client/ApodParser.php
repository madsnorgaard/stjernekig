<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Client;

/**
 * Turns a WordPress "image-article" REST item into an ApodEntry.
 *
 * NASA publishes APOD as a custom post type on science.nasa.gov. The REST
 * payload carries the full rendered article, so the interesting parts are
 * pulled out of the HTML: the hero figure or video embed, the explanation
 * paragraph and the metadata table with the credit line.
 */
final class ApodParser {

  /**
   * Tags preserved in explanation and credit HTML.
   */
  private const ALLOWED_TAGS = '<a><em><strong><b><i><br><sub><sup><cite>';

  /**
   * Parse one REST item.
   *
   * @param array<string, mixed> $item
   *   Decoded JSON for one post.
   *
   * @throws \InvalidArgumentException
   *   When the item lacks a usable date or title.
   */
  public function fromRestItem(array $item): ApodEntry {
    $date = substr((string) ($item['date'] ?? ''), 0, 10);
    if (!self::isValidDate($date)) {
      throw new \InvalidArgumentException('Source item has no usable date.');
    }

    $content = (string) ($item['content']['rendered'] ?? '');
    $title = $this->parseTitle((string) ($item['title']['rendered'] ?? ''), $content);
    if ($title === '') {
      throw new \InvalidArgumentException("Source item for $date has no title.");
    }

    [$mediaType, $imageUrl, $videoUrl] = $this->parseHero($content, (string) ($item['featured_image_url'] ?? ''));

    $explanation = $this->parseExplanation($content);
    if ($explanation === '') {
      $caption = (string) ($item['_embedded']['wp:featuredmedia'][0]['caption']['rendered'] ?? '');
      $explanation = $this->cleanExplanation($caption);
    }

    $meta = $this->parseMetaTable($content);
    $creditLabel = '';
    $creditHtml = '';
    $authors = '';
    foreach ($meta as $label => $html) {
      if ($creditLabel === '' && preg_match('/credit|copyright|license|licence/i', $label)) {
        [$creditLabel, $creditHtml] = $this->splitCreditLabel($label, $this->cleanInlineHtml($html));
      }
      elseif ($authors === '' && stripos($label, 'author') === 0) {
        $authors = self::plainText($html);
      }
    }

    $legacy = trim((string) ($item['migration_source_url'] ?? ''));
    if ($legacy === '') {
      $legacy = 'https://apod.nasa.gov/apod/ap' . date('ymd', (int) strtotime($date)) . '.html';
    }

    return new ApodEntry(
      date: $date,
      title: $title,
      explanationHtml: $explanation,
      creditLabel: $creditLabel,
      creditHtml: $creditHtml,
      authors: $authors,
      mediaType: $mediaType,
      imageUrl: $imageUrl,
      videoUrl: $videoUrl,
      sourceUrl: (string) ($item['link'] ?? ''),
      legacyUrl: $legacy,
      remoteId: (int) ($item['id'] ?? 0),
      modified: isset($item['modified_gmt']) ? (string) $item['modified_gmt'] : NULL,
    );
  }

  /**
   * Strip the "APOD: 2024 December 25 -" prefix NASA puts on every title.
   */
  public function parseTitle(string $rendered, string $content = ''): string {
    $title = self::plainText($rendered);
    $title = preg_replace('/^APOD:?\s*\d{4}\s+[[:alpha:]]+\s+\d{1,2}\s*[-–—]\s*/u', '', $title) ?? $title;
    $title = trim($title);
    if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $content, $m)) {
      $title = self::plainText($m[1]);
    }
    return $title;
  }

  /**
   * Find the hero media: a YouTube/Vimeo embed or the full resolution image.
   *
   * @return array{0: string, 1: ?string, 2: ?string}
   *   Media type, image URL, video URL.
   */
  private function parseHero(string $content, string $featuredImageUrl): array {
    $heroEnd = stripos($content, '<h1');
    $hero = $heroEnd === FALSE ? $content : substr($content, 0, $heroEnd);

    $imageUrl = NULL;
    if (preg_match('/<figure[^>]*>.*?<a[^>]+href="([^"]+)"/s', $hero, $m)) {
      $imageUrl = self::stripQuery(html_entity_decode($m[1]));
    }
    elseif (preg_match('/<img[^>]+src="([^"]+)"/', $hero, $m)) {
      $imageUrl = self::stripQuery(html_entity_decode($m[1]));
    }
    if (($imageUrl === NULL || self::isPlaceholder($imageUrl)) && $featuredImageUrl !== '' && !self::isPlaceholder($featuredImageUrl)) {
      $imageUrl = self::stripQuery($featuredImageUrl);
    }
    if ($imageUrl !== NULL && self::isPlaceholder($imageUrl)) {
      $imageUrl = NULL;
    }

    if (preg_match('/<iframe[^>]+src="([^"]+)"/i', $hero, $m)) {
      $videoUrl = html_entity_decode($m[1]);
      if (str_starts_with($videoUrl, '//')) {
        $videoUrl = 'https:' . $videoUrl;
      }
      if (preg_match('#^https?://(www\.)?(youtube(-nocookie)?\.com|youtu\.be|player\.vimeo\.com|vimeo\.com)/#i', $videoUrl)) {
        return [ApodEntry::MEDIA_VIDEO, $imageUrl, self::normaliseVideoUrl($videoUrl)];
      }
    }
    if (preg_match('/<video[^>]*>.*?<source[^>]+src="([^"]+)"/is', $hero, $m) || preg_match('/<video[^>]+src="([^"]+)"/i', $hero, $m)) {
      return [ApodEntry::MEDIA_VIDEO, $imageUrl, html_entity_decode($m[1])];
    }

    return [ApodEntry::MEDIA_IMAGE, $imageUrl, NULL];
  }

  /**
   * Extract and clean the explanation paragraph.
   */
  private function parseExplanation(string $content): string {
    if (preg_match('/<p[^>]+class="[^"]*media-detail-hero__description[^"]*"[^>]*>(.*?)<\/p>/s', $content, $m)) {
      return $this->cleanExplanation($m[1]);
    }
    if (preg_match('/<strong>\s*Explanation:\s*<\/strong>(.*?)<\/p>/s', $content, $m)) {
      return $this->cleanExplanation($m[1]);
    }
    return '';
  }

  /**
   * Remove the "Explanation:" lead-in and the "Tomorrow's picture" trailer.
   */
  private function cleanExplanation(string $html): string {
    $html = preg_replace('/^\s*(<p[^>]*>)?\s*<strong>\s*Explanation:?\s*<\/strong>\s*/i', '', $html) ?? $html;
    $html = preg_replace('/(<br\s*\/?>\s*)*<strong>\s*Tomorrow(&#8217;|&rsquo;|\'|’)s picture:?\s*<\/strong>.*$/is', '', $html) ?? $html;
    return $this->cleanInlineHtml($html);
  }

  /**
   * Keep inline formatting and links, drop everything else.
   */
  private function cleanInlineHtml(string $html): string {
    $html = strip_tags($html, self::ALLOWED_TAGS);
    $html = preg_replace_callback('/<a\b[^>]*>/i', function (array $m): string {
      if (!preg_match('/href\s*=\s*"([^"]*)"/i', $m[0], $h)) {
        return '';
      }
      $href = $this->rewriteLink(html_entity_decode($h[1]));
      return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener">';
    }, $html) ?? $html;
    // Drop closing anchors whose opening tag was removed above.
    $html = preg_replace('/<\/a>(?![\s\S]*<a\b)/i', '</a>', $html) ?? $html;
    $html = preg_replace('/<(em|strong|b|i|sub|sup|cite)\b[^>]*>/i', '<$1>', $html) ?? $html;
    $html = preg_replace('/<br\b[^>]*>/i', '<br>', $html) ?? $html;
    $html = preg_replace('/\s+/', ' ', $html) ?? $html;
    $html = preg_replace('/\s+([.,;:!?])/', '$1', $html) ?? $html;
    return trim($html);
  }

  /**
   * Point historic APOD links at this archive instead of apod.nasa.gov.
   */
  public function rewriteLink(string $href): string {
    if (preg_match('#^(?:https?://apod\.nasa\.gov/apod/)?ap(\d{2})(\d{2})(\d{2})\.html$#i', $href, $m)) {
      $year = (int) $m[1] >= 95 ? '19' . $m[1] : '20' . $m[1];
      return sprintf('/apod/%s-%s-%s', $year, $m[2], $m[3]);
    }
    if (preg_match('#^https?://apod\.nasa\.gov/apod/(astropix\.html)?$#i', $href)) {
      return '/apod';
    }
    if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
      return 'https://science.nasa.gov' . $href;
    }
    return $href;
  }

  /**
   * NASA sometimes puts "Image Credit & Copyright:" inside the value cell.
   *
   * @return array{0: string, 1: string}
   *   Label and remaining credit HTML.
   */
  private function splitCreditLabel(string $label, string $html): array {
    $pattern = '/^\s*((?:[A-Za-z]+\s+)?Credit(?:\s*(?:&amp;|&|and)\s*(?:Copyright|Licen[sc]e))?)\s*:\s*/iu';
    if (preg_match($pattern, $html, $m)) {
      $label = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $html = trim(substr($html, strlen($m[0])));
    }
    return [rtrim($label, ': '), $html];
  }

  /**
   * Read the metadata table into label => HTML pairs.
   *
   * @return array<string, string>
   *   The result.
   */
  private function parseMetaTable(string $content): array {
    $rows = [];
    if (!preg_match_all('/<tr[^>]*>\s*<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/s', $content, $matches, PREG_SET_ORDER)) {
      return $rows;
    }
    foreach ($matches as $m) {
      $label = rtrim(self::plainText($m[1]), ': ');
      if ($label !== '' && !isset($rows[$label])) {
        $rows[$label] = $m[2];
      }
    }
    return $rows;
  }

  /**
   * YouTube and Vimeo embeds without tracking parameters.
   */
  private static function normaliseVideoUrl(string $url): string {
    if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
      return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
      return 'https://player.vimeo.com/video/' . $m[1];
    }
    return $url;
  }

  /**
   * NASA's generic placeholder thumbnails are not pictures of the day.
   */
  private static function isPlaceholder(string $url): bool {
    return str_contains($url, 'news-thumbnail') || str_contains($url, '/nasa-child/assets/');
  }

  /**
   * Strip query.
   */
  private static function stripQuery(string $url): string {
    $pos = strpos($url, '?');
    return $pos === FALSE ? $url : substr($url, 0, $pos);
  }

  /**
   * Plain text.
   */
  public static function plainText(string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
  }

  /**
   * Is valid date.
   */
  public static function isValidDate(string $date): bool {
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $dt !== FALSE && $dt->format('Y-m-d') === $date;
  }

}
