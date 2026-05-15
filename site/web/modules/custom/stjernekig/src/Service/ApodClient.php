<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Service;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Talks to NASA's Astronomy Picture of the Day API.
 *
 * @see https://api.nasa.gov/
 */
final class ApodClient {

  private const API = 'https://api.nasa.gov/planetary/apod';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Fetch a single APOD entry by date (YYYY-MM-DD).
   *
   * @return array{title:string,date:string,explanation:string,copyright:string,image_url:?string}
   */
  public function fetchByDate(string $date): array {
    $apiKey = getenv('NASA_API_KEY') ?: 'DEMO_KEY';

    $response = $this->httpClient->request('GET', self::API, [
      'query' => [
        'api_key' => $apiKey,
        'date' => $date,
      ],
      'timeout' => 10,
    ]);

    $data = Json::decode((string) $response->getBody());

    return [
      'title' => $data['title'] ?? '',
      'date' => $data['date'] ?? $date,
      'explanation' => $data['explanation'] ?? '',
      'copyright' => $data['copyright'] ?? '',
      'image_url' => $data['hdurl'],
    ];
  }

}
