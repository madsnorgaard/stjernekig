<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\stjernekig\Archive\ArchiveRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Machine-readable views of the archive: RSS and a sitemap.
 */
final class FeedController extends ControllerBase {

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly ArchiveRepository $archive,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('stjernekig.archive'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * RSS 2.0 feed of the newest entries, with the picture enclosed.
   */
  public function rss(): CacheableResponse {
    $settings = $this->config('stjernekig.settings');
    $site = $this->config('system.site');
    $count = max(1, (int) $settings->get('archive.feed_items'));
    $nodes = $this->archive->loadMultiple($this->archive->latestIds($count));

    $x = new \XMLWriter();
    $x->openMemory();
    $x->setIndent(TRUE);
    $x->startDocument('1.0', 'UTF-8');
    $x->startElement('rss');
    $x->writeAttribute('version', '2.0');
    $x->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
    $x->writeAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
    $x->startElement('channel');
    $x->writeElement('title', (string) $site->get('name'));
    $x->writeElement('link', Url::fromRoute('stjernekig.archive', [], ['absolute' => TRUE])->toString());
    $x->writeElement('description', (string) $settings->get('archive.site_tagline'));
    $x->writeElement('language', 'en');
    $x->startElement('atom:link');
    $x->writeAttribute('href', Url::fromRoute('stjernekig.feed', [], ['absolute' => TRUE])->toString());
    $x->writeAttribute('rel', 'self');
    $x->writeAttribute('type', 'application/rss+xml');
    $x->endElement();
    if ($nodes) {
      $x->writeElement('lastBuildDate', date(DATE_RSS, (int) reset($nodes)->getChangedTime()));
    }

    foreach ($nodes as $node) {
      $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
      $x->startElement('item');
      $x->writeElement('title', (string) $node->label());
      $x->writeElement('link', $url);
      $x->writeElement('guid', $url);
      $x->writeElement('pubDate', date(DATE_RSS, (int) $node->getCreatedTime()));
      $description = (string) $node->get('field_apod_explanation')->value;
      $credit = trim(strip_tags((string) $node->get('field_apod_credit')->value));
      if ($credit !== '') {
        $description .= '<p><em>' . htmlspecialchars((string) $node->get('field_apod_credit_label')->value, ENT_QUOTES, 'UTF-8') . ':</em> ' . htmlspecialchars($credit, ENT_QUOTES, 'UTF-8') . '</p>';
      }
      $image = $this->imageUrl($node, 'apod_hero');
      if ($image) {
        $description = '<p><img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt=""></p>' . $description;
        $x->startElement('media:content');
        $x->writeAttribute('url', $image);
        $x->writeAttribute('medium', 'image');
        $x->endElement();
      }
      $x->writeElement('description', $description);
      $x->endElement();
    }

    $x->endElement();
    $x->endElement();
    $x->endDocument();

    return $this->xmlResponse($x->outputMemory(), 'application/rss+xml; charset=utf-8');
  }

  /**
   * One sitemap for the whole archive.
   */
  public function sitemap(): CacheableResponse {
    $x = new \XMLWriter();
    $x->openMemory();
    $x->setIndent(TRUE);
    $x->startDocument('1.0', 'UTF-8');
    $x->startElement('urlset');
    $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach (['stjernekig.archive', 'stjernekig.years', 'stjernekig.on_this_day'] as $route) {
      $x->startElement('url');
      $x->writeElement('loc', Url::fromRoute($route, [], ['absolute' => TRUE])->toString());
      $x->writeElement('changefreq', 'daily');
      $x->endElement();
    }
    foreach ($this->archive->allDates() as $date => $changed) {
      $x->startElement('url');
      $x->writeElement('loc', Url::fromUserInput('/apod/' . $date, ['absolute' => TRUE])->toString());
      $x->writeElement('lastmod', date('Y-m-d', $changed));
      $x->endElement();
    }
    $x->endElement();
    $x->endDocument();

    return $this->xmlResponse($x->outputMemory(), 'application/xml; charset=utf-8');
  }

  /**
   * Wrap XML in a cacheable response.
   */
  private function xmlResponse(string $body, string $contentType): CacheableResponse {
    $response = new CacheableResponse($body, 200, ['Content-Type' => $contentType]);
    $metadata = new CacheableMetadata();
    $metadata->setCacheTags(['node_list:apod_entry', 'config:system.site', 'config:stjernekig.settings']);
    $metadata->setCacheMaxAge(3600);
    $response->addCacheableDependency($metadata);
    return $response;
  }

  /**
   * URL of an image style derivative for the entry picture.
   */
  private function imageUrl(NodeInterface $node, string $style): ?string {
    if ($node->get('field_apod_image')->isEmpty()) {
      return NULL;
    }
    $file = $node->get('field_apod_image')->entity;
    $imageStyle = $this->entityTypeManager()->getStorage('image_style')->load($style);
    if (!$file instanceof FileInterface || !$imageStyle instanceof ImageStyleInterface) {
      return NULL;
    }
    return $imageStyle->buildUrl($file->getFileUri());
  }

}
