<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\process;

use Drupal\custom_bookplate_migrate\BookplateIdentity;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Skips existing honoree/book-title pairs before any destination changes.
 */
#[MigrateProcess(id: 'bookplate_unique')]
final class BookplateUnique extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$honoree, $book_title] = $value;
    if (!is_string($honoree) || !is_string($book_title) || trim($honoree) === '' || trim($book_title) === '') {
      throw new MigrateException('Both honoree name and book title are required to identify a bookplate safely.');
    }
    $existing = BookplateIdentity::findExisting($honoree, $book_title);
    if ($existing) {
      $message = sprintf('Skipped source bookplate %s: honoree "%s" and book title "%s" already exist in destination node(s) %s. Existing content was preserved.', $row->getSourceProperty('nid'), $honoree, $book_title, implode(', ', $existing));
      \Drupal::logger('custom_bookplate_migrate')->notice($message);
      throw new MigrateSkipRowException($message);
    }
    return $honoree;
  }

}
