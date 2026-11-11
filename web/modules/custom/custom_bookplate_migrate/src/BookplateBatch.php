<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Runs discovered migrations in bounded browser batches without Drush.
 */
final class BookplateBatch {

  public const IDS = ['bookplate_files', 'bookplate_images', 'bookplate_nodes'];
  public const RUN_KEY = 'custom_bookplate_migrate.active_run';
  public const LOCK = 'custom_bookplate_migrate.operation';

  /**
   * Reserves a GUI run and builds its dependency-ordered batch definition.
   */
  public static function start(string $operation, int $year, bool $retry_failed = FALSE): void {
    require_once DRUPAL_ROOT . '/core/includes/form.inc';
    if (!in_array($operation, ['import', 'rollback'], TRUE) || $year < 1000 || $year > 9999) {
      throw new MigrateException('Invalid bookplate migration operation or year.');
    }
    $lock = \Drupal::lock();
    if (!$lock->acquire(self::LOCK, 900)) {
      throw new MigrateException('Another bookplate migration request is running.');
    }
    try {
      \Drupal::state()->resetCache();
      if (\Drupal::state()->get(self::RUN_KEY)) {
        throw new MigrateException('A GUI run is already reserved. Resume it or reset the abandoned run first.');
      }
      foreach (self::IDS as $id) {
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($id);
        if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
          throw new MigrateException(sprintf('%s is not idle. Wait for it to finish or reset an abandoned run.', $id));
        }
      }
      $token = bin2hex(random_bytes(16));
      \Drupal::state()->set('custom_bookplate_migrate.award_year', $year);
      \Drupal::state()->set(self::RUN_KEY, [
        'token' => $token,
        'touched' => \Drupal::time()->getCurrentTime(),
      ]);
      $operations = [];
      foreach ($operation === 'rollback' ? array_reverse(self::IDS) : self::IDS as $id) {
        $operations[] = [[self::class, 'process'], [$id, $operation, $year, $token, $retry_failed]];
      }
      \batch_set([
        'title' => t('Bookplate migration: @operation', ['@operation' => $operation]),
        'operations' => $operations,
        'finished' => [self::class, 'finished'],
        'error_message' => t('The migration was interrupted. Review the migration messages before resuming or resetting an abandoned run.'),
      ]);
    }
    finally {
      $lock->release(self::LOCK);
    }
  }

  /**
   * Processes at most one file or ten other rows per HTTP request.
   */
  public static function process(string $id, string $operation, int $year, string $token, bool $retry_failed, array &$context): void {
    if (!empty($context['results']['failed'])) {
      $context['finished'] = 1;
      return;
    }
    $lock = \Drupal::lock();
    if (!$lock->acquire(self::LOCK, 900)) {
      throw new MigrateException('Another bookplate migration request is running.');
    }
    try {
      \Drupal::state()->resetCache();
      $run = \Drupal::state()->get(self::RUN_KEY);
      if (!$run || $run['token'] !== $token) {
        throw new MigrateException('This batch is no longer active. Start a new run from the Bookplate migration page.');
      }
      $run['touched'] = \Drupal::time()->getCurrentTime();
      \Drupal::state()->set(self::RUN_KEY, $run);
      $context['results']['token'] = $token;
      $migration = \Drupal::service('plugin.manager.migration')->createInstance($id, [
        'source' => ['selected_year' => $year, 'skip_processed' => TRUE],
      ]);
      $map = $migration->getIdMap();
      if (empty($context['sandbox']['initialized'])) {
        if ($operation === 'import' && $retry_failed) {
          $source = $migration->getSourcePlugin();
          $statement = $source->query()->execute();
          $statement->setFetchMode(FetchAs::Associative);
          foreach ($statement as $row) {
            $source_ids = array_intersect_key($row, $source->getIds());
            $mapped = $map->getRowBySource($source_ids);
            if ($mapped && (int) $mapped['source_row_status'] === MigrateIdMapInterface::STATUS_FAILED) {
              \Drupal::service('custom_bookplate_migrate.execution_guard')->executeGui($token, static function () use ($map, $source_ids): int {
                $map->setUpdate($source_ids);
                return 0;
              });
            }
          }
        }
        $context['sandbox'] = [
          'initialized' => TRUE,
          'total' => $operation === 'import' ? $migration->getSourcePlugin()->count() : $map->processedCount(),
          'processed' => 0,
        ];
        $context['results']['steps'][$id] = ['operation' => $operation, 'processed' => 0, 'failed' => 0];
      }
      $processed = 0;
      $event_name = $operation === 'import' ? MigrateEvents::MAP_SAVE : MigrateEvents::MAP_DELETE;
      $listener = static function (MigrateMapSaveEvent|MigrateMapDeleteEvent $event) use ($map, $migration, $id, &$processed, &$context): void {
        if ($event->getMap() !== $map) {
          return;
        }
        $processed++;
        if ($event instanceof MigrateMapSaveEvent && $event->getFields()['source_row_status'] === MigrateIdMapInterface::STATUS_FAILED) {
          $context['results']['steps'][$id]['failed']++;
        }
        if ($processed >= ($id === 'bookplate_files' ? 1 : 10)) {
          $migration->interruptMigration(MigrationInterface::RESULT_INCOMPLETE);
        }
      };
      $dispatcher = \Drupal::service('event_dispatcher');
      $dispatcher->addListener($event_name, $listener);
      try {
        $executable = new MigrateExecutable($migration, new MigrateMessage());
        $result = \Drupal::service('custom_bookplate_migrate.execution_guard')->executeGui(
          $token,
          static fn(): int => $operation === 'import' ? $executable->import() : $executable->rollback(),
        );
      }
      finally {
        $dispatcher->removeListener($event_name, $listener);
      }
      $context['sandbox']['processed'] += $processed;
      $context['results']['steps'][$id]['processed'] += $processed;
      $context['message'] = t('@migration: @count rows processed in this run.', [
        '@migration' => $id,
        '@count' => $context['sandbox']['processed'],
      ]);
      $context['finished'] = $result === MigrationInterface::RESULT_INCOMPLETE
        ? min(0.99, $context['sandbox']['processed'] / max(1, $context['sandbox']['total']))
        : 1;
      if (!in_array($result, [MigrationInterface::RESULT_COMPLETED, MigrationInterface::RESULT_INCOMPLETE], TRUE)
        || $context['results']['steps'][$id]['failed'] > 0) {
        $context['results']['failed'] = TRUE;
        $context['finished'] = 1;
      }
    }
    finally {
      $lock->release(self::LOCK);
    }
  }

  /**
   * Reports failures as failures and releases only this batch's reservation.
   */
  public static function finished(bool $success, array $results, array $operations): void {
    $run = \Drupal::state()->get(self::RUN_KEY);
    if (isset($results['token']) && $run && $run['token'] === $results['token']) {
      \Drupal::state()->delete(self::RUN_KEY);
    }
    foreach ($results['steps'] ?? [] as $id => $step) {
      \Drupal::messenger()->addStatus(t('@id: @operation processed @count rows; @failed failed.', [
        '@id' => $id,
        '@operation' => $step['operation'],
        '@count' => $step['processed'],
        '@failed' => $step['failed'],
      ]));
    }
    if (!$success || !empty($results['failed'])) {
      \Drupal::messenger()->addError(t('Migration did not complete successfully. Later steps were not run. Review Messages and fix the cause before retrying. An interrupted migration may need an abandoned-run reset.'));
    }
    elseif (($results['steps']['bookplate_nodes']['operation'] ?? NULL) === 'rollback') {
      \Drupal::messenger()->addStatus(t('Bookplate rollback finished. Skipped existing content and award-year terms were preserved.'));
    }
    else {
      \Drupal::messenger()->addStatus(t('Bookplate migration finished. Review Messages for skipped existing bookplates and verify the imported content.'));
    }
  }

}
