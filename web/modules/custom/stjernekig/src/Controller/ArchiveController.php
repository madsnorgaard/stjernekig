<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\stjernekig\Archive\ArchiveRepository;
use Drupal\stjernekig\Form\SearchForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public archive pages.
 */
final class ArchiveController extends ControllerBase {

  private const LIST_CACHE = ['tags' => ['node_list:apod_entry'], 'contexts' => ['url.query_args', 'url.path']];

  private const ATTACHED = ['library' => ['stjernekig/archive']];

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ArchiveRepository $archive,
    private readonly PagerManagerInterface $pagerManager,
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    FormBuilderInterface $formBuilder,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('stjernekig.archive'),
      $container->get('pager.manager'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('form_builder'),
    );
  }

  /**
   * Route /apod: today's picture in full, then the days before it.
   */
  public function latest(Request $request): array {
    $perPage = $this->archive->itemsPerPage();
    $total = $this->archive->countAll();
    if ($total === 0) {
      return $this->emptyArchive();
    }

    $page = $this->pagerManager->createPager(max(0, $total - 1), $perPage)->getCurrentPage();
    $build = ['#cache' => self::LIST_CACHE, '#attached' => self::ATTACHED];

    if ($page === 0) {
      $node = $this->archive->latest();
      if ($node) {
        $build['hero'] = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
        $build['hero']['#attributes']['class'][] = 'apod-latest';
      }
      $build['heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Earlier pictures'),
        '#attributes' => ['class' => ['apod-section-title']],
      ];
    }
    else {
      $build['heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Earlier pictures, page @page', ['@page' => $page + 1]),
        '#attributes' => ['class' => ['apod-section-title']],
      ];
    }

    $ids = $this->archive->latestIds($perPage, $page * $perPage + 1);
    $build['grid'] = $this->grid($ids);
    $build['pager'] = ['#type' => 'pager', '#quantity' => 5];
    return $build;
  }

  /**
   * Route /apod/YYYY-MM-DD without an alias: a real entry or a gap page.
   */
  public function entry(string $date): array {
    if (!self::validDate($date)) {
      throw new NotFoundHttpException();
    }
    $node = $this->archive->byDate($date);
    if ($node) {
      $build = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
      $build['#cache']['tags'][] = 'node_list:apod_entry';
      return $build;
    }

    $start = (string) $this->config('stjernekig.settings')->get('archive.start_date');
    $last = $this->archive->lastDate();
    if ($date < $start || $last === NULL || $date > $last) {
      throw new NotFoundHttpException();
    }

    $neighbours = $this->archive->neighbours($date);
    return [
      '#theme' => 'stjernekig_gap',
      '#date' => $date,
      '#formatted_date' => $this->formatDate($date),
      '#prev' => $neighbours['prev'] ? $this->cardLink($neighbours['prev']) : NULL,
      '#next' => $neighbours['next'] ? $this->cardLink($neighbours['next']) : NULL,
      '#cache' => ['tags' => ['node_list:apod_entry']],
      '#attached' => self::ATTACHED,
    ];
  }

  /**
   * Route /apod/today: jump to the newest entry.
   */
  public function today(): RedirectResponse {
    $node = $this->archive->latest();
    return new RedirectResponse($node ? $node->toUrl()->toString() : Url::fromRoute('stjernekig.archive')->toString());
  }

  /**
   * Route /apod/random: jump somewhere in the last three decades.
   */
  public function random(): RedirectResponse {
    $node = $this->archive->random();
    $response = new RedirectResponse($node ? $node->toUrl()->toString() : Url::fromRoute('stjernekig.archive')->toString());
    $response->setPrivate()->setMaxAge(0);
    $response->headers->set('Cache-Control', 'no-store');
    return $response;
  }

  /**
   * Route /apod/on-this-day: the same calendar day across all years.
   */
  public function onThisDay(Request $request): array {
    $monthDay = (string) $request->query->get('date', date('m-d'));
    if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $monthDay)) {
      $monthDay = date('m-d');
    }
    $ids = $this->archive->onThisDayIds($monthDay);
    $label = (new \DateTimeImmutable('2000-' . $monthDay))->format('j F');

    return [
      '#cache' => self::LIST_CACHE + ['max-age' => 3600],
      '#attached' => self::ATTACHED,
      'intro' => [
        '#theme' => 'stjernekig_intro',
        '#title' => $this->t('Every picture published on @day', ['@day' => $label]),
        '#text' => $this->formatPlural(
          count($ids),
          'One picture so far.',
          '@count pictures across the years.',
        ),
      ],
      'grid' => $ids ? $this->grid($ids) : $this->empty($this->t('Nothing has been published on this day yet.')),
    ];
  }

  /**
   * Route /apod/archive: all years.
   */
  public function years(): array {
    $counts = $this->archive->yearCounts();
    $years = [];
    foreach ($counts as $year => $count) {
      $years[] = [
        'year' => $year,
        'count' => $count,
        'url' => Url::fromRoute('stjernekig.year', ['year' => $year])->toString(),
      ];
    }
    return [
      '#cache' => self::LIST_CACHE,
      '#attached' => self::ATTACHED,
      'intro' => [
        '#theme' => 'stjernekig_intro',
        '#title' => $this->t('The archive'),
        '#text' => $this->formatPlural(
          $this->archive->countAll(),
          'One picture, starting @first.',
          '@count pictures, from @first to @last.',
          [
            '@first' => $this->formatDate($this->archive->firstDate()),
            '@last' => $this->formatDate($this->archive->lastDate()),
          ],
        ),
      ],
      'years' => [
        '#theme' => 'stjernekig_years',
        '#years' => $years,
      ],
    ];
  }

  /**
   * Route /apod/YYYY: months of the year plus every picture, newest first.
   */
  public function year(string $year): array {
    $y = (int) $year;
    $counts = $this->archive->monthCounts($y);
    if (!$counts) {
      throw new NotFoundHttpException();
    }
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
      $months[] = [
        'month' => $m,
        'label' => (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('F'),
        'count' => $counts[$m] ?? 0,
        'url' => isset($counts[$m]) ? Url::fromRoute('stjernekig.month', [
          'year' => sprintf('%04d', $y),
          'month' => sprintf('%02d', $m),
        ])->toString() : NULL,
      ];
    }
    $perPage = $this->archive->itemsPerPage() * 2;
    $total = $this->archive->countYear($y);
    $page = $this->pagerManager->createPager($total, $perPage)->getCurrentPage();

    return [
      '#cache' => self::LIST_CACHE,
      '#attached' => self::ATTACHED,
      'months' => [
        '#theme' => 'stjernekig_months',
        '#months' => $months,
        '#year' => $y,
        '#prev_year' => $this->yearLink($y - 1),
        '#next_year' => $this->yearLink($y + 1),
      ],
      'grid' => $this->grid($this->archive->yearIds($y, $perPage, $page * $perPage)),
      'pager' => ['#type' => 'pager', '#quantity' => 5],
    ];
  }

  /**
   * Route /apod/YYYY/MM: a calendar with one thumbnail per day.
   */
  public function month(string $year, string $month): array {
    $y = (int) $year;
    $m = (int) $month;
    if ($m < 1 || $m > 12) {
      throw new NotFoundHttpException();
    }
    $entries = $this->archive->monthEntries($y, $m);
    if (!$entries) {
      throw new NotFoundHttpException();
    }
    $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m));
    $daysInMonth = (int) $first->format('t');
    // ISO weekday: Monday = 1.
    $leading = (int) $first->format('N') - 1;

    $cells = array_fill(0, $leading, NULL);
    for ($day = 1; $day <= $daysInMonth; $day++) {
      $cell = ['day' => $day, 'url' => NULL, 'image' => NULL, 'title' => NULL];
      if (isset($entries[$day])) {
        $node = $entries[$day];
        $cell['url'] = $node->toUrl()->toString();
        $cell['title'] = $node->label();
        $cell['image'] = $this->thumb($node, 'apod_thumb');
      }
      $cells[] = $cell;
    }

    $prev = $first->modify('-1 month');
    $next = $first->modify('+1 month');
    return [
      '#cache' => self::LIST_CACHE,
      '#attached' => self::ATTACHED,
      'calendar' => [
        '#theme' => 'stjernekig_calendar',
        '#year' => $y,
        '#month' => $m,
        '#month_label' => $first->format('F Y'),
        '#weekdays' => [
          $this->t('Mon'), $this->t('Tue'), $this->t('Wed'), $this->t('Thu'),
          $this->t('Fri'), $this->t('Sat'), $this->t('Sun'),
        ],
        '#cells' => $cells,
        '#prev' => $this->monthLink($prev),
        '#next' => $this->monthLink($next),
        '#year_url' => Url::fromRoute('stjernekig.year', ['year' => sprintf('%04d', $y)])->toString(),
      ],
    ];
  }

  /**
   * Route /apod/search?q=: substring search.
   */
  public function search(Request $request): array {
    $keywords = trim((string) $request->query->get('q', ''));
    $perPage = $this->archive->itemsPerPage();
    $build = [
      '#cache' => self::LIST_CACHE,
      '#attached' => self::ATTACHED,
      'form' => $this->formBuilder->getForm(SearchForm::class),
    ];
    if ($keywords === '') {
      return $build;
    }
    $result = $this->archive->search($keywords, $perPage, 0);
    $page = $this->pagerManager->createPager($result['total'], $perPage)->getCurrentPage();
    if ($page > 0) {
      $result = $this->archive->search($keywords, $perPage, $page * $perPage);
    }
    $build['intro'] = [
      '#theme' => 'stjernekig_intro',
      '#title' => $this->formatPlural(
        $result['total'],
        'One picture matches "@q"',
        '@count pictures match "@q"',
        ['@q' => $keywords],
      ),
      '#text' => '',
    ];
    $build['grid'] = $result['ids'] ? $this->grid($result['ids']) : $this->empty($this->t('Try a different word: object names, constellations, telescopes and photographers all work.'));
    $build['pager'] = ['#type' => 'pager', '#quantity' => 5];
    return $build;
  }

  /**
   * Title callback for a date without an alias (a gap page).
   */
  public function entryTitle(string $date): string {
    return self::validDate($date) ? $this->formatDate($date) : (string) $this->t('Astronomy Picture of the Day');
  }

  /**
   * Title callback for the year page.
   */
  public function yearTitle(string $year): string {
    return (string) $this->t('Pictures from @year', ['@year' => $year]);
  }

  /**
   * Title callback for the month page.
   */
  public function monthTitle(string $year, string $month): string {
    $m = max(1, min(12, (int) $month));
    return (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $year, $m)))->format('F Y');
  }

  /**
   * A grid of teaser cards.
   *
   * @param int[] $ids
   *   Node IDs in display order.
   */
  private function grid(array $ids): array {
    $items = [];
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    foreach ($this->archive->loadMultiple($ids) as $node) {
      $items[] = $viewBuilder->view($node, 'teaser');
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['apod-grid']],
      '#attached' => ['library' => ['stjernekig/archive']],
    ];
  }

  /**
   * A short "nothing here" paragraph.
   */
  private function empty(string|\Stringable $text): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $text,
      '#attributes' => ['class' => ['apod-empty']],
    ];
  }

  /**
   * Shown before the first import has run.
   */
  private function emptyArchive(): array {
    return [
      '#cache' => ['tags' => ['node_list:apod_entry']],
      'intro' => [
        '#theme' => 'stjernekig_intro',
        '#title' => $this->t('The archive is empty'),
        '#text' => $this->t('Run the importer to fetch the first pictures: drush stjernekig:import 7'),
      ],
    ];
  }

  /**
   * Link data for a neighbouring entry.
   *
   * @return array{url: string, title: string, date: string, image: ?array}
   *   URL, title, formatted date and thumbnail render array.
   */
  private function cardLink(NodeInterface $node): array {
    return [
      'url' => $node->toUrl()->toString(),
      'title' => (string) $node->label(),
      'date' => $this->formatDate((string) $node->get('field_apod_date')->value),
      'image' => $this->thumb($node, 'apod_thumb'),
    ];
  }

  /**
   * Image style render array for an entry, or NULL without a picture.
   */
  private function thumb(NodeInterface $node, string $style): ?array {
    if ($node->get('field_apod_image')->isEmpty()) {
      return NULL;
    }
    $file = $node->get('field_apod_image')->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    return [
      '#theme' => 'image_style',
      '#style_name' => $style,
      '#uri' => $file->getFileUri(),
      '#alt' => (string) $node->label(),
      '#attributes' => ['loading' => 'lazy'],
    ];
  }

  /**
   * URL of a year page when that year has entries.
   */
  private function yearLink(int $year): ?string {
    return $this->archive->countYear($year) > 0 ? Url::fromRoute('stjernekig.year', ['year' => sprintf('%04d', $year)])->toString() : NULL;
  }

  /**
   * Label and URL of a month page when that month has entries.
   *
   * @return array{label: string, url: string}|null
   *   Month label and URL, or NULL when the month is empty.
   */
  private function monthLink(\DateTimeImmutable $month): ?array {
    if (empty($this->archive->monthCounts((int) $month->format('Y'))[(int) $month->format('n')])) {
      return NULL;
    }
    return [
      'label' => $month->format('F Y'),
      'url' => Url::fromRoute('stjernekig.month', ['year' => $month->format('Y'), 'month' => $month->format('m')])->toString(),
    ];
  }

  /**
   * Human readable date.
   */
  private function formatDate(?string $date): string {
    return $date ? (new \DateTimeImmutable($date))->format('j F Y') : '';
  }

  /**
   * Strict YYYY-MM-DD check.
   */
  private static function validDate(string $date): bool {
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $dt !== FALSE && $dt->format('Y-m-d') === $date;
  }

}
