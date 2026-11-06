<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Row;

/**
 * Reads bookplates and their first field items from the legacy database.
 */
#[MigrateSource(id: 'bookplate_nodes')]
final class BookplateNodes extends BookplateSourceBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->bookplates()->orderBy('n.nid');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid', 'title', 'status', 'created', 'changed', 'langcode', 'book_title',
      'award_year', 'field_book_author', 'field_bp_department', 'field_ebook',
      'field_is_author', 'field_isbn', 'field_occasion', 'field_position_role',
      'field_why_chosen', 'field_link_to_record', 'field_book_cover',
      'field_honoree_photo',
    ];
    return array_combine($fields, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['nid' => ['type' => 'integer', 'alias' => 'n']];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    foreach ([
      'field_book_author', 'field_bp_department', 'field_ebook', 'field_is_author',
      'field_isbn', 'field_occasion', 'field_position_role', 'field_why_chosen',
      'field_link_to_record', 'field_book_cover', 'field_honoree_photo',
    ] as $field) {
      $item = $this->select('node__' . $field, 'f')
        ->fields('f')
        ->condition('f.entity_id', $row->getSourceProperty('nid'))
        ->condition('f.langcode', $row->getSourceProperty('langcode'))
        ->condition('f.deleted', 0)
        ->condition('f.delta', 0)
        ->execute()
        ->fetchAssoc();
      $values = [];
      foreach ($item ?: [] as $column => $value) {
        if (str_starts_with($column, $field . '_')) {
          $values[substr($column, strlen($field) + 1)] = $value;
        }
      }
      $row->setSourceProperty($field, $values);
    }
    return parent::prepareRow($row);
  }

}
