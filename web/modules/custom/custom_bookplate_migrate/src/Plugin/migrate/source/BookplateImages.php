<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;

/**
 * Reads image media referenced by eligible bookplates.
 */
#[MigrateSource(id: 'bookplate_images')]
final class BookplateImages extends BookplateSourceBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->images();
    $query->fields('m', ['mid', 'name', 'status', 'created', 'changed']);
    foreach (['target_id', 'alt', 'title', 'width', 'height'] as $property) {
      $query->addField('i', 'field_media_image_' . $property, 'image_' . $property);
    }
    return $query->distinct()->orderBy('m.mid');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'mid', 'name', 'status', 'created', 'changed', 'image_target_id',
      'image_alt', 'image_title', 'image_width', 'image_height',
    ];
    return array_combine($fields, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['mid' => ['type' => 'integer', 'alias' => 'm']];
  }

}
