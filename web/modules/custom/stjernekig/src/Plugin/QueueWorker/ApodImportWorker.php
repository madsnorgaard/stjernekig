<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\stjernekig\Client\ApodClientException;
use Drupal\stjernekig\Importer\ApodImporter;
use Drupal\stjernekig\Importer\ImportResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports one date per queue item. Cron fills the queue, see stjernekig_cron().
 */
#[QueueWorker(
  id: 'stjernekig_apod_import',
  title: new TranslatableMarkup('Stjernekig APOD import'),
  cron: ['time' => 60],
)]
final class ApodImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly ApodImporter $importer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stjernekig.importer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $date = is_array($data) ? (string) ($data['date'] ?? '') : (string) $data;
    if ($date === '') {
      return;
    }
    try {
      $result = $this->importer->importDate($date, !empty($data['update']));
    }
    catch (ApodClientException $e) {
      // The source is unreachable: stop draining and try again next cron.
      throw new SuspendQueueException($e->getMessage(), 0, $e);
    }
    if ($result === ImportResult::Failed) {
      throw new \RuntimeException("Import of $date failed; see the stjernekig log channel.");
    }
  }

}
