<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Client;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Reads Astronomy Pictures of the Day from NASA's WordPress REST API.
 *
 * The historical api.nasa.gov/planetary/apod endpoint scraped the retired
 * apod.nasa.gov pages and no longer returns real data. The canonical source
 * is now science.nasa.gov, where every APOD since 1995 is an "image-article"
 * post in the APOD category.
 */
final class ApodClient {

  private const ENDPOINT = '/wp-json/wp/v2/image-article';

  private const FIELDS = 'id,date,modified_gmt,slug,link,title,content,featured_image_url,migration_source_url';

  public const MAX_PER_PAGE = 100;

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ApodParser $parser,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Fetch the entry for one date, or NULL when the source has none.
   */
  public function fetchByDate(string $date): ?ApodEntry {
    self::assertDate($date);
    $result = $this->fetchRange($date, $date, 1, 5, 'desc');
    foreach ($result['entries'] as $entry) {
      if ($entry->date === $date) {
        return $entry;
      }
    }
    return NULL;
  }

  /**
   * The newest entries, newest first.
   *
   * @return \Drupal\stjernekig\Client\ApodEntry[]
   *   The result.
   */
  public function fetchLatest(int $count = 7): array {
    $count = max(1, min(self::MAX_PER_PAGE, $count));
    return $this->fetchPage(1, $count, 'desc')['entries'];
  }

  /**
   * Entries between two dates (inclusive), one page at a time.
   *
   * @return array{entries: \Drupal\stjernekig\Client\ApodEntry[], total: int, pages: int}
   *   The result.
   */
  public function fetchRange(string $start, string $end, int $page = 1, int $perPage = self::MAX_PER_PAGE, string $order = 'asc'): array {
    self::assertDate($start);
    self::assertDate($end);
    $after = (new \DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d\T23:59:59');
    $before = (new \DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d\T00:00:00');
    return $this->request([
      'after' => $after,
      'before' => $before,
      'page' => $page,
      'per_page' => $perPage,
      'order' => $order,
    ]);
  }

  /**
   * One page of the whole archive.
   *
   * @return array{entries: \Drupal\stjernekig\Client\ApodEntry[], total: int, pages: int}
   *   The result.
   */
  public function fetchPage(int $page = 1, int $perPage = self::MAX_PER_PAGE, string $order = 'asc'): array {
    return $this->request([
      'page' => $page,
      'per_page' => $perPage,
      'order' => $order,
    ]);
  }

  /**
   * Total number of entries the source holds.
   */
  public function countAll(): int {
    return $this->fetchPage(1, 1)['total'];
  }

  /**
   * Pause between bulk requests so the import stays a polite citizen.
   */
  public function throttle(): void {
    $ms = (int) $this->config()->get('source.request_delay_ms');
    if ($ms > 0) {
      usleep($ms * 1000);
    }
  }

  /**
   * Perform one request against the image-article endpoint.
   *
   * @param array<string, int|string> $query
   *   Query parameters; category, orderby and _fields are added.
   *
   * @return array{entries: \Drupal\stjernekig\Client\ApodEntry[], total: int, pages: int}
   *   Parsed entries plus the pagination headers.
   */
  private function request(array $query): array {
    $config = $this->config();
    $base = rtrim((string) $config->get('source.base_url'), '/');
    $query += [
      'categories' => (int) $config->get('source.category_id'),
      'orderby' => 'date',
      '_fields' => self::FIELDS,
    ];

    try {
      $response = $this->httpClient->request('GET', $base . self::ENDPOINT, [
        'query' => $query,
        'timeout' => (int) ($config->get('source.timeout') ?: 30),
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'Stjernekig/1.0 (+https://github.com/madsnorgaard/stjernekig)',
        ],
      ]);
    }
    catch (GuzzleException $e) {
      // A page past the end is a 400 "rest_post_invalid_page_number"; treat
      // it as an empty page so callers can loop until nothing comes back.
      if (method_exists($e, 'getResponse') && $e->getResponse()?->getStatusCode() === 400 && (int) ($query['page'] ?? 1) > 1) {
        return ['entries' => [], 'total' => 0, 'pages' => 0];
      }
      throw new ApodClientException('APOD source request failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
    }

    $items = Json::decode((string) $response->getBody());
    if (!is_array($items)) {
      throw new ApodClientException('APOD source returned something that is not JSON.');
    }

    $entries = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      try {
        $entries[] = $this->parser->fromRestItem($item);
      }
      catch (\InvalidArgumentException $e) {
        $this->logger->warning('Skipped unparsable source item @id: @message', [
          '@id' => $item['id'] ?? '?',
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return [
      'entries' => $entries,
      'total' => (int) ($response->getHeaderLine('X-WP-Total') ?: count($entries)),
      'pages' => (int) ($response->getHeaderLine('X-WP-TotalPages') ?: 1),
    ];
  }

  /**
   * Config.
   */
  private function config(): ImmutableConfig {
    return $this->configFactory->get('stjernekig.settings');
  }

  /**
   * Guard against malformed dates.
   */
  private static function assertDate(string $date): void {
    if (!ApodParser::isValidDate($date)) {
      throw new \InvalidArgumentException("'$date' is not a YYYY-MM-DD date.");
    }
  }

}
