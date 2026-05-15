<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Commands;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\stjernekig\Service\ApodClient;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Stjernekig.
 */
final class StjernekigCommands extends DrushCommands {

  public function __construct(
    private readonly ApodClient $apodClient,
    private readonly QueueFactory $queueFactory,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
    parent::__construct();
  }

  /**
   * Enqueue and immediately import the last N days of APOD.
   */
  #[CLI\Command(name: 'stjernekig:import', aliases: ['sk:import'])]
  #[CLI\Argument(name: 'count', description: 'Number of past days to import.')]
  #[CLI\Usage(name: 'drush stjernekig:import 10', description: 'Import the last 10 days.')]
  public function import(int $count = 10): void {
    $queue = $this->queueFactory->get('stjernekig_apod_import');
    $worker = $this->queueWorkerManager->createInstance('stjernekig_apod_import');

    for ($i = 0; $i < $count; $i++) {
      $date = date('Y-m-d', strtotime("-{$i} days"));
      $queue->createItem(['date' => $date]);
    }

    $this->logger()->success(dt('Enqueued @n APOD imports.', ['@n' => $count]));

    $processed = 0;
    while ($item = $queue->claimItem()) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Throwable $e) {
        $queue->releaseItem($item);
        $this->logger()->error(dt('Queue item failed: @message', ['@message' => $e->getMessage()]));
      }
    }

    $this->logger()->success(dt(
      'Processed @n queue items. Run `drush wd-show` to inspect any silent failures.',
      ['@n' => $processed],
    ));
  }

  /**
   * Fetch a single APOD date and print the parsed payload (debugging helper).
   */
  #[CLI\Command(name: 'stjernekig:peek', aliases: ['sk:peek'])]
  #[CLI\Argument(name: 'date', description: 'YYYY-MM-DD (defaults to today).')]
  public function peek(?string $date = NULL): void {
    $date = $date ?? date('Y-m-d');
    $payload = $this->apodClient->fetchByDate($date);
    $this->output()->writeln(print_r($payload, TRUE));
  }

}
