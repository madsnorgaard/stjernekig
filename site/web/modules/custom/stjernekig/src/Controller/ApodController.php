<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stjernekig archive page controller.
 */
final class ApodController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  /**
   * Renders the APOD archive as a tile grid, newest first.
   */
  public function archive(): array {
    $storage = $this->entityTypes->getStorage('node');
    $view_builder = $this->entityTypes->getViewBuilder('node');

    $ids = $storage->getQuery()
      ->condition('type', 'apod_entry')
      ->condition('status', 1)
      ->sort('field_apod_date', 'DESC')
      ->range(0, 60)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($ids)) {
      return [
        '#markup' => '<p>No APOD entries yet. Run <code>drush stjernekig:import 10</code> to fetch the latest.</p>',
      ];
    }

    $items = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $items[] = $view_builder->view($node, 'teaser');
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['stjernekig-archive']],
      '#cache' => [
        'tags' => ['node_list:apod_entry'],
      ],
    ];
  }

}
