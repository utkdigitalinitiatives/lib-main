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

}
