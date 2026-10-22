<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\stjernekig\Archive\ArchiveRepository;
use Drupal\stjernekig\Importer\ApodImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coverage report at /admin/reports/stjernekig.
 */
final class ReportController extends ControllerBase {

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ArchiveRepository $archive,
    private readonly QueueFactory $queueFactory,
    private readonly DateFormatterInterface $dateFormatter,
    StateInterface $state,
  ) {
    $this->stateService = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('stjernekig.archive'),
      $container->get('queue'),
      $container->get('date.formatter'),
      $container->get('state'),
    );
  }

  /**
   * Print the totals of an import run.
   */
  public function report(): array {
    $first = $this->archive->firstDate();
    $last = $this->archive->lastDate();
    $start = (string) $this->config('stjernekig.settings')->get('archive.start_date');
    $today = date('Y-m-d');
    $missing = $this->archive->missingDates($start, $today);
    $known = (array) $this->state()->get(ApodImporter::STATE_KNOWN_MISSING, []);
    $unexplained = array_values(array_diff($missing, array_keys($known)));
    $lastImport = $this->state()->get(ApodImporter::STATE_LAST_IMPORT);
    $lastError = $this->state()->get(ApodImporter::STATE_LAST_ERROR);

    $rows = [
      [$this->t('Entries stored'), number_format($this->archive->countAll())],
      [$this->t('First entry'), $first ?? $this->t('none')],
      [$this->t('Newest entry'), $last ?? $this->t('none')],
      [$this->t('Days without an entry since @start', ['@start' => $start]), number_format(count($missing))],
      [$this->t('... of which the source also has none'), number_format(count($known))],
      [$this->t('... still worth retrying'), number_format(count($unexplained))],
      [$this->t('Queued imports'), (string) $this->queueFactory->get('stjernekig_apod_import')->numberOfItems()],
      [
        $this->t('Last successful import'),
        $lastImport ? $this->t('@date, @ago ago', [
          '@date' => $lastImport['date'],
          '@ago' => $this->dateFormatter->formatTimeDiffSince($lastImport['time']),
        ]) : $this->t('never'),
      ],
      [
        $this->t('Last error'),
        $lastError ? $this->t('@date: @message (@ago ago)', [
          '@date' => $lastError['date'],
          '@message' => $lastError['message'],
          '@ago' => $this->dateFormatter->formatTimeDiffSince($lastError['time']),
        ]) : $this->t('none recorded'),
      ],
    ];

    $build['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Metric'), $this->t('Value')],
      '#rows' => $rows,
    ];
    if ($unexplained) {
      $build['gaps'] = [
        '#type' => 'details',
        '#title' => $this->t('Dates to retry (@count)', ['@count' => count($unexplained)]),
        'list' => [
          '#theme' => 'item_list',
          '#items' => array_slice($unexplained, 0, 500),
        ],
        'hint' => [
          '#markup' => '<p><code>drush stjernekig:fill-gaps</code></p>',
        ],
      ];
    }
    $build['#cache']['max-age'] = 0;
    return $build;
  }

}
