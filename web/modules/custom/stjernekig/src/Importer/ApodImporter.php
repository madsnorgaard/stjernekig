<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Importer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\stjernekig\Client\ApodClient;
use Drupal\stjernekig\Client\ApodEntry;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Turns ApodEntry objects into apod_entry nodes with local media.
 */
final class ApodImporter {

  public const BUNDLE = 'apod_entry';

  public const STATE_LAST_IMPORT = 'stjernekig.last_import';

  public const STATE_LAST_ERROR = 'stjernekig.last_error';

  /**
   * Dates the source confirmed it has no entry for (APOD skipped some days).
   */
  public const STATE_KNOWN_MISSING = 'stjernekig.known_missing';

  /**
   * Content types the source serves that we are willing to store.
   */
  private const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
  ];

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ApodClient $client,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Fetch one date from the source and store it.
   */
  public function importDate(string $date, bool $update = FALSE): ImportResult {
    if (!$update && $this->loadByDate($date)) {
      return ImportResult::Skipped;
    }
    $entry = $this->client->fetchByDate($date);
    if ($entry === NULL) {
      $this->rememberMissing($date);
      return ImportResult::Missing;
    }
    return $this->import($entry, $update);
  }

  /**
   * Dates known to have no entry at the source.
   *
   * @return array<string, int>
   *   Date => timestamp of the check.
   */
  public function knownMissing(): array {
    return (array) $this->state->get(self::STATE_KNOWN_MISSING, []);
  }

  /**
   * Clear the known-missing list.
   */
  public function forgetMissing(): void {
    $this->state->delete(self::STATE_KNOWN_MISSING);
  }

  /**
   * Record that the source has no entry for a date.
   */
  public function rememberMissing(string $date): void {
    $known = $this->knownMissing();
    $known[$date] = time();
    ksort($known);
    $this->state->set(self::STATE_KNOWN_MISSING, $known);
  }

  /**
   * Store one entry as a node, downloading its media.
   *
   * @param \Drupal\stjernekig\Client\ApodEntry $entry
   *   The entry.
   * @param bool $update
   *   Refresh an existing node instead of skipping it.
   */
  public function import(ApodEntry $entry, bool $update = FALSE): ImportResult {
    $existing = $this->loadByDate($entry->date);
    if ($existing && !$update) {
      return ImportResult::Skipped;
    }

    try {
      $file = $this->fetchMedia($entry, $existing);

      /** @var \Drupal\node\NodeInterface $node */
      $node = $existing ?? $this->entityTypeManager->getStorage('node')->create([
        'type' => self::BUNDLE,
        'uid' => 1,
        'status' => 1,
      ]);
      $node->setTitle(mb_substr($entry->title, 0, 255));
      $node->set('field_apod_date', $entry->date);
      $node->set('field_apod_explanation', ['value' => $entry->explanationHtml, 'format' => 'apod_html']);
      $node->set('field_apod_credit', ['value' => $entry->creditHtml, 'format' => 'apod_html']);
      $node->set('field_apod_credit_label', mb_substr($entry->creditLabel, 0, 255));
      $node->set('field_apod_authors', mb_substr($entry->authors, 0, 255));
      $node->set('field_apod_media_type', $entry->mediaType);
      $node->set('field_apod_remote_url', $entry->imageUrl);
      $node->set('field_apod_video_url', $entry->videoUrl);
      $node->set('field_apod_source_url', $entry->sourceUrl);
      $node->set('field_apod_legacy_url', $entry->legacyUrl);
      $node->set('field_apod_remote_id', $entry->remoteId);
      $node->set('field_apod_copyrighted', $entry->isCopyrighted());
      $node->set('field_apod_image', $file ? ['target_id' => $file->id(), 'alt' => mb_substr($entry->title, 0, 512)] : NULL);
      $node->set('created', (int) strtotime($entry->date . ' 12:00:00 UTC'));
      $node->save();

      $this->ensureAlias($node, $entry->date);
      $this->state->set(self::STATE_LAST_IMPORT, ['date' => $entry->date, 'time' => time()]);

      $this->logger->info('@verb APOD @date: @title', [
        '@verb' => $existing ? 'Updated' : 'Imported',
        '@date' => $entry->date,
        '@title' => $entry->title,
      ]);
      return $existing ? ImportResult::Updated : ImportResult::Created;
    }
    catch (\Throwable $e) {
      $this->state->set(self::STATE_LAST_ERROR, [
        'date' => $entry->date,
        'time' => time(),
        'message' => $e->getMessage(),
      ]);
      $this->logger->error('APOD import failed for @date: @message', [
        '@date' => $entry->date,
        '@message' => $e->getMessage(),
      ]);
      return ImportResult::Failed;
    }
  }

  /**
   * Load the node for a date, if it exists.
   */
  public function loadByDate(string $date): ?NodeInterface {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('field_apod_date', $date)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->entityTypeManager->getStorage('node')->load(reset($ids));
    return $node;
  }

  /**
   * Download the picture (or a video still) unless settings or rights say no.
   */
  private function fetchMedia(ApodEntry $entry, ?NodeInterface $existing): ?FileInterface {
    if ($entry->imageUrl === NULL) {
      return NULL;
    }
    $config = $this->configFactory->get('stjernekig.settings');
    if ($entry->isCopyrighted() && !$config->get('media.store_copyrighted')) {
      return NULL;
    }

    // Keep the file we already have when refreshing metadata.
    if ($existing && !$existing->get('field_apod_image')->isEmpty()) {
      $current = $existing->get('field_apod_remote_url')->value;
      if ($current === $entry->imageUrl) {
        /** @var \Drupal\file\FileInterface|null $file */
        $file = $existing->get('field_apod_image')->entity;
        return $file;
      }
    }

    try {
      $response = $this->httpClient->request('GET', $entry->imageUrl, [
        'timeout' => 120,
        'headers' => ['User-Agent' => 'Stjernekig/1.0 (+https://github.com/madsnorgaard/stjernekig)'],
      ]);
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException('Media download failed: ' . $e->getMessage(), 0, $e);
    }

    $mime = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
    if (!isset(self::IMAGE_TYPES[$mime])) {
      throw new \RuntimeException("Media is $mime, not an image we store.");
    }
    $body = $response->getBody();
    $max = (int) $config->get('media.max_bytes');
    $length = (int) $response->getHeaderLine('Content-Length');
    if ($max > 0 && $length > $max) {
      throw new \RuntimeException("Media is $length bytes, above the configured limit.");
    }
    $contents = $body->getContents();
    if ($max > 0 && strlen($contents) > $max) {
      throw new \RuntimeException('Media exceeds the configured size limit.');
    }
    if ($contents === '') {
      throw new \RuntimeException('Media download was empty.');
    }

    $directory = 'public://apod/' . substr($entry->date, 0, 4) . '/' . substr($entry->date, 5, 2);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException("Cannot create $directory.");
    }

    $basename = basename((string) parse_url($entry->imageUrl, PHP_URL_PATH));
    $basename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $basename) ?: 'apod';
    $extension = self::IMAGE_TYPES[$mime];
    if (!preg_match('/\.' . preg_quote($extension, '/') . '$/i', $basename) && !($extension === 'jpg' && preg_match('/\.jpeg$/i', $basename))) {
      $basename = preg_replace('/\.[A-Za-z0-9]+$/', '', $basename) . '.' . $extension;
    }
    $destination = $directory . '/' . $entry->date . '-' . $basename;

    return $this->fileRepository->writeData($contents, $destination, FileExists::Replace);
  }

  /**
   * Give every entry a stable /apod/YYYY-MM-DD address.
   */
  private function ensureAlias(NodeInterface $node, string $date): void {
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $path = '/node/' . $node->id();
    $alias = '/apod/' . $date;
    $existing = $storage->loadByProperties(['path' => $path]);
    foreach ($existing as $item) {
      if ($item->get('alias')->value === $alias) {
        return;
      }
      $item->delete();
    }
    $storage->create(['path' => $path, 'alias' => $alias, 'langcode' => 'und'])->save();
  }

}
