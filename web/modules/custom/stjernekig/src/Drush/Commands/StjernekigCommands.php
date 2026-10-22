<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\stjernekig\Archive\ArchiveRepository;
use Drupal\stjernekig\Client\ApodClient;
use Drupal\stjernekig\Client\ApodParser;
use Drupal\stjernekig\Importer\ApodImporter;
use Drupal\stjernekig\Importer\ImportResult;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Importer commands: drush stjernekig:* (alias sk:*).
 */
final class StjernekigCommands extends DrushCommands {

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ApodClient $client,
    private readonly ApodImporter $importer,
    private readonly ArchiveRepository $archive,
    private readonly QueueFactory $queueFactory,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Instantiates the class from the container.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('stjernekig.client'),
      $container->get('stjernekig.importer'),
      $container->get('stjernekig.archive'),
      $container->get('queue'),
      $container->get('config.factory'),
    );
  }

  /**
   * Import the newest N entries.
   */
  #[CLI\Command(name: 'stjernekig:import', aliases: ['sk:import'])]
  #[CLI\Argument(name: 'count', description: 'How many of the newest entries to import (max 100).')]
  #[CLI\Option(name: 'update', description: 'Refresh entries that already exist.')]
  #[CLI\Usage(name: 'drush stjernekig:import 7', description: 'Import the last week.')]
  public function import(int $count = 7, array $options = ['update' => FALSE]): void {
    $entries = $this->client->fetchLatest($count);
    $this->importEntries($entries, (bool) $options['update']);
  }

  /**
   * Import one date.
   */
  #[CLI\Command(name: 'stjernekig:import-date', aliases: ['sk:date'])]
  #[CLI\Argument(name: 'date', description: 'YYYY-MM-DD')]
  #[CLI\Option(name: 'update', description: 'Refresh the entry if it already exists.')]
  public function importDate(string $date, array $options = ['update' => FALSE]): void {
    $this->assertDate($date);
    $result = $this->importer->importDate($date, (bool) $options['update']);
    $this->report([$result->value => 1]);
  }

  /**
   * Import every entry between two dates (inclusive).
   */
  #[CLI\Command(name: 'stjernekig:import-range', aliases: ['sk:range'])]
  #[CLI\Argument(name: 'start', description: 'YYYY-MM-DD')]
  #[CLI\Argument(name: 'end', description: 'YYYY-MM-DD, defaults to today.')]
  #[CLI\Option(name: 'update', description: 'Refresh entries that already exist.')]
  #[CLI\Usage(name: 'drush stjernekig:import-range 2024-01-01 2024-12-31', description: 'Import all of 2024.')]
  public function importRange(string $start, ?string $end = NULL, array $options = ['update' => FALSE]): void {
    $end ??= date('Y-m-d');
    $this->assertDate($start);
    $this->assertDate($end);
    $totals = [];
    $page = 1;
    do {
      $result = $this->client->fetchRange($start, $end, $page);
      if ($page === 1) {
        $this->io()->writeln(sprintf('Source has %d entries between %s and %s (%d pages).', $result['total'], $start, $end, $result['pages']));
      }
      $this->importEntries($result['entries'], (bool) $options['update'], $totals, sprintf('page %d/%d', $page, max(1, $result['pages'])));
      $page++;
      $this->client->throttle();
    } while ($page <= $result['pages'] && $result['entries']);
    $this->report($totals);
  }

  /**
   * Import the whole archive, oldest first. Safe to re-run.
   */
  #[CLI\Command(name: 'stjernekig:backfill', aliases: ['sk:backfill'])]
  #[CLI\Option(name: 'from', description: 'Start date, defaults to the configured archive start (1995-06-16).')]
  #[CLI\Option(name: 'to', description: 'End date, defaults to today.')]
  #[CLI\Option(name: 'update', description: 'Refresh entries that already exist.')]
  #[CLI\Option(name: 'limit', description: 'Stop after importing this many new entries (0 = no limit).')]
  #[CLI\Usage(name: 'drush stjernekig:backfill', description: 'Fetch everything since 1995. Takes a few hours and several gigabytes.')]
  #[CLI\Usage(
    name: 'drush stjernekig:backfill --from=2020-01-01 --limit=500',
    description: 'Fill in 2020 onwards, 500 entries at a time.',
  )]
  public function backfill(array $options = ['from' => NULL, 'to' => NULL, 'update' => FALSE, 'limit' => 0]): void {
    $from = $options['from'] ?: (string) $this->configFactory->get('stjernekig.settings')->get('archive.start_date');
    $to = $options['to'] ?: date('Y-m-d');
    $this->assertDate($from);
    $this->assertDate($to);
    $limit = (int) $options['limit'];
    $totals = [];
    $page = 1;
    $started = microtime(TRUE);
    do {
      $result = $this->client->fetchRange($from, $to, $page);
      if ($page === 1) {
        $this->io()->writeln(sprintf('Backfilling %d entries from %s to %s (%d pages of %d).', $result['total'], $from, $to, $result['pages'], ApodClient::MAX_PER_PAGE));
      }
      $this->importEntries($result['entries'], (bool) $options['update'], $totals, sprintf('page %d/%d', $page, max(1, $result['pages'])));
      $done = ($totals['created'] ?? 0) + ($totals['updated'] ?? 0);
      if ($limit > 0 && $done >= $limit) {
        $this->io()->writeln("Reached --limit=$limit, stopping. Re-run to continue.");
        break;
      }
      $page++;
      $this->client->throttle();
    } while ($page <= $result['pages'] && $result['entries']);
    $this->report($totals);
    $this->io()->writeln(sprintf('Took %s.', gmdate('H:i:s', (int) (microtime(TRUE) - $started))));
  }

  /**
   * Find days with no entry and try to import them.
   */
  #[CLI\Command(name: 'stjernekig:fill-gaps', aliases: ['sk:gaps'])]
  #[CLI\Option(name: 'since', description: 'Only look at dates from here on (YYYY-MM-DD). Defaults to the archive start.')]
  #[CLI\Option(name: 'retry-known', description: 'Also retry dates the source previously said it has no entry for.')]
  #[CLI\Option(name: 'dry-run', description: 'List the gaps without importing.')]
  public function fillGaps(array $options = ['since' => NULL, 'retry-known' => FALSE, 'dry-run' => FALSE]): void {
    $since = $options['since'] ?: (string) $this->configFactory->get('stjernekig.settings')->get('archive.start_date');
    $this->assertDate($since);
    $missing = $this->archive->missingDates($since, date('Y-m-d'));
    if (!$options['retry-known']) {
      $missing = array_values(array_diff($missing, array_keys($this->importer->knownMissing())));
    }
    if (!$missing) {
      $this->io()->success('No gaps.');
      return;
    }
    $this->io()->writeln(sprintf('%d dates without an entry since %s.', count($missing), $since));
    if ($options['dry-run']) {
      $this->io()->listing($missing);
      return;
    }
    // Consecutive gaps become one range request, isolated days one each.
    $runs = self::contiguousRuns($missing);
    $this->io()->writeln(sprintf('Grouped into %d request(s).', count($runs)));
    $totals = [];
    $bar = $this->io()->createProgressBar(count($runs));
    foreach ($runs as [$start, $end]) {
      if ($start === $end) {
        $result = $this->importer->importDate($start);
        $totals[$result->value] = ($totals[$result->value] ?? 0) + 1;
      }
      else {
        $seen = [];
        $page = 1;
        do {
          $result = $this->client->fetchRange($start, $end, $page);
          foreach ($result['entries'] as $entry) {
            $seen[$entry->date] = TRUE;
            $outcome = $this->importer->import($entry);
            $totals[$outcome->value] = ($totals[$outcome->value] ?? 0) + 1;
          }
          $page++;
          $this->client->throttle();
        } while ($page <= $result['pages'] && $result['entries']);
        for ($cursor = new \DateTimeImmutable($start); $cursor <= new \DateTimeImmutable($end); $cursor = $cursor->modify('+1 day')) {
          $day = $cursor->format('Y-m-d');
          if (!isset($seen[$day])) {
            $this->importer->rememberMissing($day);
            $totals['missing'] = ($totals['missing'] ?? 0) + 1;
          }
        }
      }
      $bar->advance();
      $this->client->throttle();
    }
    $bar->finish();
    $this->io()->newLine();
    $this->report($totals);
  }

  /**
   * Group sorted dates into [start, end] runs of consecutive days.
   *
   * @param string[] $dates
   *   Sorted YYYY-MM-DD dates.
   *
   * @return array<int, array{0: string, 1: string}>
   *   Inclusive runs.
   */
  public static function contiguousRuns(array $dates): array {
    $runs = [];
    $start = NULL;
    $prev = NULL;
    foreach ($dates as $date) {
      if ($prev !== NULL && (new \DateTimeImmutable($prev))->modify('+1 day')->format('Y-m-d') === $date) {
        $prev = $date;
        continue;
      }
      if ($start !== NULL) {
        $runs[] = [$start, $prev];
      }
      $start = $prev = $date;
    }
    if ($start !== NULL) {
      $runs[] = [$start, $prev];
    }
    return $runs;
  }

  /**
   * Show what is in the archive and what is not.
   */
  #[CLI\Command(name: 'stjernekig:status', aliases: ['sk:status'])]
  public function status(): void {
    $start = (string) $this->configFactory->get('stjernekig.settings')->get('archive.start_date');
    $missing = $this->archive->missingDates($start, date('Y-m-d'));
    $known = $this->importer->knownMissing();
    $rows = [
      ['Entries stored', number_format($this->archive->countAll())],
      ['First entry', $this->archive->firstDate() ?? '-'],
      ['Newest entry', $this->archive->lastDate() ?? '-'],
      ['Days without an entry', number_format(count($missing))],
      ['... known empty at the source', number_format(count($known))],
      ['... worth retrying', number_format(count(array_diff($missing, array_keys($known))))],
      ['Queued imports', (string) $this->queueFactory->get('stjernekig_apod_import')->numberOfItems()],
    ];
    try {
      $rows[] = ['Entries at the source', number_format($this->client->countAll())];
    }
    catch (\Throwable $e) {
      $rows[] = ['Entries at the source', 'unreachable: ' . $e->getMessage()];
    }
    $this->io()->table(['Metric', 'Value'], $rows);
  }

  /**
   * Fetch one date from the source and print what the parser made of it.
   */
  #[CLI\Command(name: 'stjernekig:peek', aliases: ['sk:peek'])]
  #[CLI\Argument(name: 'date', description: 'YYYY-MM-DD, defaults to the newest entry.')]
  public function peek(?string $date = NULL): void {
    if ($date === NULL) {
      $entries = $this->client->fetchLatest(1);
      $entry = $entries[0] ?? NULL;
    }
    else {
      $this->assertDate($date);
      $entry = $this->client->fetchByDate($date);
    }
    if ($entry === NULL) {
      $this->io()->warning('The source has no entry for that date.');
      return;
    }
    $rows = [];
    foreach ($entry->toArray() as $key => $value) {
      $rows[] = [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value];
    }
    $this->io()->table(['Field', 'Value'], $rows);
  }

  /**
   * Forget which dates the source said were empty, so fill-gaps retries them.
   */
  #[CLI\Command(name: 'stjernekig:forget-missing', aliases: ['sk:forget'])]
  public function forgetMissing(): void {
    $this->importer->forgetMissing();
    $this->io()->success('Cleared the list of known-empty dates.');
  }

  /**
   * Import a batch and print one marker per entry.
   *
   * @param \Drupal\stjernekig\Client\ApodEntry[] $entries
   *   Entries to store.
   * @param bool $update
   *   Refresh existing entries.
   * @param array<string, int> $totals
   *   Running totals per result, updated in place.
   * @param string $label
   *   Progress label printed after the batch.
   */
  private function importEntries(array $entries, bool $update, array &$totals = [], string $label = ''): void {
    foreach ($entries as $entry) {
      $result = $this->importer->import($entry, $update);
      $totals[$result->value] = ($totals[$result->value] ?? 0) + 1;
      $marker = match ($result) {
        ImportResult::Created => '<info>+</info>',
        ImportResult::Updated => '<comment>~</comment>',
        ImportResult::Skipped => '.',
        ImportResult::Failed => '<error>!</error>',
        ImportResult::Missing => '?',
      };
      if ($this->output()->isVerbose() || $result === ImportResult::Failed) {
        $this->io()->writeln(sprintf('%s %s %s %s', $marker, $entry->date, $entry->title, $label));
      }
      else {
        $this->output()->write($marker);
      }
    }
    if (!$this->output()->isVerbose()) {
      $this->output()->writeln(' ' . $label);
    }
    if (func_num_args() < 3) {
      $this->report($totals);
    }
  }

  /**
   * Print the totals of an import run.
   *
   * @param array<string, int> $totals
   *   Count per ImportResult value.
   */
  private function report(array $totals): void {
    $parts = [];
    foreach (['created', 'updated', 'skipped', 'missing', 'failed'] as $key) {
      if (!empty($totals[$key])) {
        $parts[] = $totals[$key] . ' ' . $key;
      }
    }
    $line = $parts ? implode(', ', $parts) : 'nothing to do';
    if (!empty($totals['failed'])) {
      $this->io()->warning($line . '. See drush watchdog:show --type=stjernekig for details.');
    }
    else {
      $this->io()->success($line . '.');
    }
  }

  /**
   * Guard against malformed dates.
   */
  private function assertDate(string $date): void {
    if (!ApodParser::isValidDate($date)) {
      throw new \InvalidArgumentException("'$date' is not a YYYY-MM-DD date.");
    }
  }

}
