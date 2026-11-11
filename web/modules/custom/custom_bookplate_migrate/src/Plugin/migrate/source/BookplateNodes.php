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
   * First-item columns retained for each source field.
   */
  private const FIELD_COLUMNS = [
    'field_book_author' => ['value', 'format'],
    'field_bp_department' => ['value', 'format'],
    'field_ebook' => ['value'],
    'field_is_author' => ['value'],
    'field_isbn' => ['value'],
    'field_occasion' => ['value', 'format'],
    'field_position_role' => ['value', 'format'],
    'field_why_chosen' => ['value', 'format'],
    'field_link_to_record' => ['uri', 'title', 'options'],
    'field_book_cover' => ['target_id'],
    'field_honoree_photo' => ['target_id'],
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->bookplates();
    foreach (self::FIELD_COLUMNS as $field => $columns) {
      $alias = 'f_' . $field;
      $query->leftJoin(
        'node__' . $field,
        $alias,
        "$alias.entity_id = n.nid AND $alias.langcode = n.langcode AND $alias.deleted = 0 AND $alias.delta = 0",
      );
      $query->addField($alias, 'entity_id', $field . '__present');
      foreach ($columns as $column) {
        $query->addField($alias, $field . '_' . $column, $field . '__' . $column);
      }
    }
    return $query->orderBy('n.nid');
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
    foreach (self::FIELD_COLUMNS as $field => $columns) {
      $values = [];
      if ($row->getSourceProperty($field . '__present') !== NULL) {
        foreach ($columns as $column) {
          $values[$column] = $row->getSourceProperty($field . '__' . $column);
        }
      }
      $row->setSourceProperty($field, $values);
    }
    return parent::prepareRow($row);
  }

}
