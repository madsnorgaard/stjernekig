<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Importer;

/**
 * What happened to one entry during import.
 */
enum ImportResult: string {
  case Created = 'created';
  case Updated = 'updated';
  case Skipped = 'skipped';
  case Missing = 'missing';
  case Failed = 'failed';
}
