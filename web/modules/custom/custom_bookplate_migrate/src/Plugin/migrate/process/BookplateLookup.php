<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Allows absent images, but fails rather than silently dropping broken ones.
 */
#[MigrateProcess(id: 'bookplate_lookup')]
final class BookplateLookup extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '' || $value === []) {
      return NULL;
    }
    $this->configuration['no_stub'] = TRUE;
    $result = parent::transform($value, $migrate_executable, $row, $destination_property);
    if ($result === NULL) {
      throw new MigrateException(sprintf('Referenced source ID %s has not been imported by %s.', $value, implode(', ', (array) $this->configuration['migration'])));
    }
    return $result;
  }

}
