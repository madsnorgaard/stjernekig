<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Archive;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Read-side queries over the archive.
 *
 * Everything here is cacheable by callers using the node_list:apod_entry tag.
 */
final class ArchiveRepository {

  public const BUNDLE = 'apod_entry';

  private const DATE_TABLE = 'node__field_apod_date';

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Cards per listing page, from settings.
   */
  public function itemsPerPage(): int {
    return max(1, (int) $this->configFactory->get('stjernekig.settings')->get('archive.items_per_page'));
  }

  /**
   * Number of published entries.
   */
  public function countAll(): int {
    return (int) $this->baseQuery()->countQuery()->execute()->fetchField();
  }

  /**
   * Oldest date in the archive.
   */
  public function firstDate(): ?string {
    $value = $this->baseQuery()->fields('d', ['field_apod_date_value'])->orderBy('d.field_apod_date_value', 'ASC')->range(0, 1)->execute()->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

  /**
   * Newest date in the archive.
   */
  public function lastDate(): ?string {
    $value = $this->baseQuery()->fields('d', ['field_apod_date_value'])->orderBy('d.field_apod_date_value', 'DESC')->range(0, 1)->execute()->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

  /**
   * Node IDs newest first.
   *
   * @return int[]
   *   The result.
   */
  public function latestIds(int $limit, int $offset = 0): array {
    return $this->fetchIds($this->baseQuery()->orderBy('d.field_apod_date_value', 'DESC')->range($offset, $limit));
  }

  /**
   * The newest entry.
   */
  public function latest(): ?NodeInterface {
    $ids = $this->latestIds(1);
    return $ids ? $this->load($ids[0]) : NULL;
  }

  /**
   * The entry for one date.
   */
  public function byDate(string $date): ?NodeInterface {
    $ids = $this->fetchIds($this->baseQuery()->condition('d.field_apod_date_value', $date)->range(0, 1));
    return $ids ? $this->load($ids[0]) : NULL;
  }

  /**
   * The nearest entries before and after a date.
   *
   * @return array{prev: ?\Drupal\node\NodeInterface, next: ?\Drupal\node\NodeInterface}
   *   The result.
   */
  public function neighbours(string $date): array {
    $prev = $this->fetchIds($this->baseQuery()->condition('d.field_apod_date_value', $date, '<')->orderBy('d.field_apod_date_value', 'DESC')->range(0, 1));
    $next = $this->fetchIds($this->baseQuery()->condition('d.field_apod_date_value', $date, '>')->orderBy('d.field_apod_date_value', 'ASC')->range(0, 1));
    return [
      'prev' => $prev ? $this->load($prev[0]) : NULL,
      'next' => $next ? $this->load($next[0]) : NULL,
    ];
  }

  /**
   * Any published entry.
   */
  public function random(): ?NodeInterface {
    $ids = $this->fetchIds($this->baseQuery()->orderRandom()->range(0, 1));
    return $ids ? $this->load($ids[0]) : NULL;
  }

  /**
   * Every entry published on a given month and day, newest first.
   *
   * @return int[]
   *   The result.
   */
  public function onThisDayIds(string $monthDay): array {
    $query = $this->baseQuery()
      ->condition('d.field_apod_date_value', '%-' . $this->database->escapeLike($monthDay), 'LIKE')
      ->orderBy('d.field_apod_date_value', 'DESC');
    return $this->fetchIds($query);
  }

  /**
   * Query result.
   *
   * @return array<int, int>
   *   Year => number of entries, newest year first.
   */
  public function yearCounts(): array {
    $query = $this->baseQuery();
    $query->addExpression('SUBSTRING(d.field_apod_date_value, 1, 4)', 'y');
    $query->addExpression('COUNT(*)', 'c');
    $query->groupBy('y')->orderBy('y', 'DESC');
    $counts = [];
    foreach ($query->execute() as $row) {
      $counts[(int) $row->y] = (int) $row->c;
    }
    return $counts;
  }

  /**
   * Query result.
   *
   * @return array<int, int>
   *   Month => number of entries for the year.
   */
  public function monthCounts(int $year): array {
    $query = $this->baseQuery()->condition('d.field_apod_date_value', sprintf('%04d-%%', $year), 'LIKE');
    $query->addExpression('SUBSTRING(d.field_apod_date_value, 6, 2)', 'm');
    $query->addExpression('COUNT(*)', 'c');
    $query->groupBy('m')->orderBy('m', 'ASC');
    $counts = [];
    foreach ($query->execute() as $row) {
      $counts[(int) $row->m] = (int) $row->c;
    }
    return $counts;
  }

  /**
   * Number of entries in a year.
   */
  public function countYear(int $year): int {
    return (int) $this->baseQuery()->condition('d.field_apod_date_value', sprintf('%04d-%%', $year), 'LIKE')->countQuery()->execute()->fetchField();
  }

  /**
   * Query result.
   *
   * @return int[]
   *   The result.
   */
  public function yearIds(int $year, int $limit, int $offset = 0): array {
    return $this->fetchIds($this->baseQuery()
      ->condition('d.field_apod_date_value', sprintf('%04d-%%', $year), 'LIKE')
      ->orderBy('d.field_apod_date_value', 'DESC')
      ->range($offset, $limit));
  }

  /**
   * Query result.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   Day of month => node.
   */
  public function monthEntries(int $year, int $month): array {
    $query = $this->baseQuery()
      ->fields('d', ['entity_id', 'field_apod_date_value'])
      ->condition('d.field_apod_date_value', sprintf('%04d-%02d-%%', $year, $month), 'LIKE')
      ->orderBy('d.field_apod_date_value', 'ASC');
    $byDay = [];
    foreach ($query->execute() as $row) {
      $byDay[(int) substr((string) $row->field_apod_date_value, 8, 2)] = (int) $row->entity_id;
    }
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($byDay);
    $result = [];
    foreach ($byDay as $day => $nid) {
      if (isset($nodes[$nid])) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $nodes[$nid];
        $result[$day] = $node;
      }
    }
    return $result;
  }

  /**
   * Plain substring search over title and explanation.
   *
   * @return array{ids: int[], total: int}
   *   The result.
   */
  public function search(string $keywords, int $limit, int $offset = 0): array {
    $keywords = trim($keywords);
    if (mb_strlen($keywords) < 2) {
      return ['ids' => [], 'total' => 0];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $build = function () use ($storage, $keywords) {
      $query = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', self::BUNDLE)
        ->condition('status', 1);
      $group = $query->orConditionGroup()
        ->condition('title', $keywords, 'CONTAINS')
        ->condition('field_apod_explanation', $keywords, 'CONTAINS')
        ->condition('field_apod_credit', $keywords, 'CONTAINS');
      return $query->condition($group);
    };
    $total = (int) $build()->count()->execute();
    $ids = $total ? array_values(array_map('intval', $build()->sort('field_apod_date', 'DESC')->range($offset, $limit)->execute())) : [];
    return ['ids' => $ids, 'total' => $total];
  }

  /**
   * Dates in a range that have no entry.
   *
   * @return string[]
   *   The result.
   */
  public function missingDates(string $from, string $to): array {
    $have = $this->baseQuery()
      ->fields('d', ['field_apod_date_value'])
      ->condition('d.field_apod_date_value', [$from, $to], 'BETWEEN')
      ->execute()->fetchCol();
    $have = array_fill_keys($have, TRUE);
    $missing = [];
    $cursor = new \DateTimeImmutable($from);
    $end = new \DateTimeImmutable($to);
    while ($cursor <= $end) {
      $day = $cursor->format('Y-m-d');
      if (!isset($have[$day])) {
        $missing[] = $day;
      }
      $cursor = $cursor->modify('+1 day');
    }
    return $missing;
  }

  /**
   * All dates in the archive, oldest first. Used by the sitemap.
   *
   * @return array<string, int>
   *   Date => changed timestamp.
   */
  public function allDates(): array {
    $query = $this->baseQuery()->fields('d', ['field_apod_date_value'])->fields('n', ['changed'])->orderBy('d.field_apod_date_value', 'ASC');
    $dates = [];
    foreach ($query->execute() as $row) {
      $dates[(string) $row->field_apod_date_value] = (int) $row->changed;
    }
    return $dates;
  }

  /**
   * Load nodes in the order of the given IDs.
   *
   * @param int[] $ids
   *   Node IDs.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Nodes keyed by ID, in the order requested.
   */
  public function loadMultiple(array $ids): array {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $id) {
      if (isset($nodes[$id])) {
        $ordered[$id] = $nodes[$id];
      }
    }
    return $ordered;
  }

  /**
   * Load one node.
   */
  private function load(int $id): ?NodeInterface {
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    return $node;
  }

  /**
   * Published apod_entry nodes joined to their date, aliased n and d.
   */
  private function baseQuery(): SelectInterface {
    $query = $this->database->select('node_field_data', 'n');
    $query->join(self::DATE_TABLE, 'd', 'd.entity_id = n.nid AND d.deleted = 0');
    $query->condition('n.type', self::BUNDLE)->condition('n.status', 1);
    return $query;
  }

  /**
   * Query result.
   *
   * @return int[]
   *   The result.
   */
  private function fetchIds(SelectInterface $query): array {
    $query->fields('n', ['nid']);
    return array_map('intval', $query->execute()->fetchCol());
  }

}
