<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;

/**
 * Reads files used by eligible bookplate image media.
 */
#[MigrateSource(id: 'bookplate_files')]
final class BookplateFiles extends BookplateSourceBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->images();
    $query->innerJoin('file_managed', 'f', 'f.fid = i.field_media_image_target_id');
    $query->fields('f', ['fid', 'filename', 'uri', 'filemime', 'status', 'created', 'changed']);
    return $query->distinct()->orderBy('f.fid');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = ['fid', 'filename', 'uri', 'filemime', 'status', 'created', 'changed'];
    return array_combine($fields, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['fid' => ['type' => 'integer', 'alias' => 'f']];
  }

}
