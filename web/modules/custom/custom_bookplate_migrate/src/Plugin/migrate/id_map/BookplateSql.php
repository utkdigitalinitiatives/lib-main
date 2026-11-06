<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\id_map;

use Drupal\Component\Plugin\Attribute\PluginID;
use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Guards pre-import map updates without changing SQL map storage or IDs.
 */
#[PluginID('bookplate_sql')]
final class BookplateSql extends Sql {

  /**
   * {@inheritdoc}
   */
  public function prepareUpdate() {
    \Drupal::service('custom_bookplate_migrate.execution_guard')->updateMap(fn() => parent::prepareUpdate());
  }

  /**
   * {@inheritdoc}
   */
  public function setUpdate(array $source_id_values) {
    \Drupal::service('custom_bookplate_migrate.execution_guard')->updateMap(fn() => parent::setUpdate($source_id_values));
  }

}
