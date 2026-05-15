<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\file\FileRepositoryInterface;
use Drupal\stjernekig\Service\ApodClient;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports a single APOD entry as a node.
 *
 * @QueueWorker(
 *   id = "stjernekig_apod_import",
 *   title = @Translation("Stjernekig APOD import"),
 *   cron = {"time" = 30}
 * )
 */
final class ApodImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly ApodClient $apodClient,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stjernekig.apod_client'),
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('file.repository'),
      $container->get('file_system'),
      $container->get('logger.channel.stjernekig'),
    );
  }

  public function processItem($data): void {
    $date = $data['date'] ?? date('Y-m-d');

    $existing = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'apod_entry',
      'field_apod_date' => $date,
    ]);
    if (!empty($existing)) {
      return;
    }

    try {
      $payload = $this->apodClient->fetchByDate($date);

      $directory = 'public://apod/' . substr($date, 0, 7);
      $this->fileSystem->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
      );

      $contents = $this->httpClient->get($payload['image_url'])->getBody()->getContents();
      $filename = basename(parse_url($payload['image_url'], PHP_URL_PATH) ?? 'apod.jpg');
      $file = $this->fileRepository->writeData($contents, $directory . '/' . $filename);

      $this->entityTypeManager->getStorage('node')->create([
        'type' => 'apod_entry',
        'title' => $payload['title'],
        'field_apod_date' => $payload['date'],
        'field_apod_explanation' => [
          'value' => $payload['explanation'],
          'format' => 'basic_html',
        ],
        'field_apod_copyright' => $payload['copyright'],
        'field_apod_image' => [
          'target_id' => $file->id(),
          'alt' => $payload['title'],
        ],
        'status' => 1,
      ])->save();

      $this->logger->info('Imported APOD entry for @date: @title', [
        '@date' => $date,
        '@title' => $payload['title'],
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('APOD import failed for @date: @message', [
        '@date' => $date,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
