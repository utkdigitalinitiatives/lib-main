<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\custom_bookplate_migrate\BookplateIdentity;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Shares year and image eligibility across the bookplate migrations.
 */
abstract class BookplateSourceBase extends SqlBase {

  /**
   * Filters mapped rows before core can clear messages or hydrate field data.
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $data) {
      if (!empty($this->configuration['skip_processed'])) {
        $mapped = $this->idMap->getRowBySource(array_intersect_key($data, $this->getIds()));
        if ($mapped && (int) $mapped['source_row_status'] !== MigrateIdMapInterface::STATUS_NEEDS_UPDATE) {
          continue;
        }
      }
      yield $data;
    }
  }

  /**
   * Resolves the selected year without requiring exported configuration.
   */
  protected function awardYear(): string {
    $year = $this->configuration['selected_year'] ?? $this->state->get('custom_bookplate_migrate.award_year', $this->configuration['award_year'] ?? 2026);
    if ((!is_int($year) && !is_string($year)) || !preg_match('/^[1-9][0-9]{3}$/D', (string) $year)) {
      throw new MigrateException('Bookplate award year must be a four-digit year.');
    }
    return (string) $year;
  }

  /**
   * Selects published, default-language bookplates in the selected year.
   */
  protected function bookplates(): SelectInterface {
    $query = $this->select('node_field_data', 'n');
    $query->innerJoin('node__field_award_year', 'y', 'y.entity_id = n.nid AND y.langcode = n.langcode AND y.deleted = 0 AND y.delta = 0');
    $query->innerJoin('taxonomy_term_field_data', 't', 't.tid = y.field_award_year_target_id AND t.default_langcode = 1');
    $query->leftJoin('node__field_book_title', 'b', 'b.entity_id = n.nid AND b.langcode = n.langcode AND b.deleted = 0 AND b.delta = 0');
    $query->fields('n', ['nid', 'title', 'status', 'created', 'changed', 'langcode']);
    $query->addField('b', 'field_book_title_value', 'book_title');
    $query->addField('t', 'name', 'award_year');
    $query->condition('n.type', 'bookplate');
    $query->condition('n.status', 1);
    $query->condition('n.default_langcode', 1);
    $query->condition('t.vid', 'bookplate_year');
    $query->condition('t.name', $this->awardYear());
    return $query;
  }

  /**
   * Selects images only for bookplates not already in the destination.
   */
  protected function images(): SelectInterface {
    $node_ids = [];
    $statement = $this->bookplates()->execute();
    $statement->setFetchMode(FetchAs::Associative);
    foreach ($statement as $node) {
      if (trim($node['title']) !== '' && trim($node['book_title'] ?? '') !== ''
        && !BookplateIdentity::findExisting($node['title'], $node['book_title'])) {
        $node_ids[] = $node['nid'];
      }
    }

    $references = [];
    foreach (['field_book_cover', 'field_honoree_photo'] as $field) {
      $reference = $this->select('node__' . $field, 'r')
        ->fields('r', [$field . '_target_id'])
        ->condition('r.entity_id', $node_ids ?: [0], 'IN')
        ->condition('r.deleted', 0)
        ->condition('r.delta', 0);
      $reference->innerJoin('node_field_data', 'rn', 'rn.nid = r.entity_id AND rn.langcode = r.langcode AND rn.default_langcode = 1');
      $references[] = $reference;
    }
    $references[0]->union($references[1]);

    $query = $this->select('media_field_data', 'm');
    $query->innerJoin('media__field_media_image', 'i', 'i.entity_id = m.mid AND i.langcode = m.langcode AND i.deleted = 0 AND i.delta = 0');
    $query->condition('m.mid', $references[0], 'IN');
    $query->condition('m.bundle', 'image');
    $query->condition('m.default_langcode', 1);
    return $query;
  }

}
