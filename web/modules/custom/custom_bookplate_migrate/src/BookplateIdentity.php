<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate;

/**
 * Matches bookplates without relying on IDs shared between sites.
 */
final class BookplateIdentity {

  /**
   * Finds all matching destination nodes, including unpublished content.
   */
  public static function findExisting(string $honoree, string $book_title): array {
    return \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'bookplate')
      ->condition('title', $honoree)
      ->condition('field_book_title.value', $book_title)
      ->execute();
  }

  /**
   * Finds existing pairs with bounded queries and returns their input indexes.
   *
   * @param list<array{0: string, 1: string}> $pairs
   *
   * @return array<int, true>
   */
  public static function findExistingPairs(array $pairs): array {
    if (!$pairs) {
      return [];
    }

    $unique_pairs = [];
    $indexes_by_pair = [];
    foreach ($pairs as $index => [$honoree, $book_title]) {
      $key = serialize([$honoree, $book_title]);
      $unique_pairs[$key] = [$honoree, $book_title];
      $indexes_by_pair[$key][] = $index;
    }

    $existing_pairs = [];
    foreach (array_chunk($unique_pairs, 100, TRUE) as $batch) {
      $query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'bookplate');
      $conditions = $query->orConditionGroup();
      foreach ($batch as [$honoree, $book_title]) {
        $conditions->condition(
          $query->andConditionGroup()
            ->condition('title', $honoree)
            ->condition('field_book_title.value', $book_title),
        );
      }
      $ids = $query->condition($conditions)->execute();
      if (!$ids) {
        continue;
      }

      foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids) as $node) {
        $honorees = [];
        $book_titles = [];
        foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
          $translation = $node->getTranslation($langcode);
          $honorees[] = $translation->label();
          foreach ($translation->get('field_book_title') as $item) {
            $book_titles[] = $item->value;
          }
        }
        // Match findExisting()'s unscoped language conditions.
        foreach ($honorees as $honoree) {
          foreach ($book_titles as $book_title) {
            $key = serialize([$honoree, $book_title]);
            if (isset($batch[$key])) {
              $existing_pairs[$key] = TRUE;
            }
          }
        }
      }
    }

    $existing_indexes = [];
    foreach ($indexes_by_pair as $key => $indexes) {
      if (isset($existing_pairs[$key])) {
        foreach ($indexes as $index) {
          $existing_indexes[$index] = TRUE;
        }
      }
    }
    return $existing_indexes;
  }

}
