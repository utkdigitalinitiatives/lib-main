<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\EventSubscriber;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\custom_bookplate_migrate\BookplateBatch;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Serializes bookplate execution across GUI, Drush, and core entry points.
 */
final class BookplateExecutionGuard implements EventSubscriberInterface {

  /**
   * The token authorized by the currently executing GUI batch request.
   */
  private ?string $guiToken = NULL;

  /**
   * The non-GUI migration owning the shared request lock.
   */
  private ?MigrationInterface $owner = NULL;

  public function __construct(
    private readonly LockBackendInterface $lock,
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_IMPORT => ['before', 1000],
      MigrateEvents::PRE_ROLLBACK => ['before', 1000],
      MigrateEvents::POST_IMPORT => ['after', -1000],
      MigrateEvents::POST_ROLLBACK => ['after', -1000],
    ];
  }

  /**
   * Authorizes only the GUI execution inside its already-locked batch request.
   */
  public function executeGui(string $token, callable $execute): int {
    if ($this->guiToken !== NULL || $this->owner !== NULL) {
      throw new MigrateException('Another bookplate migration is already executing in this request.');
    }
    $this->guiToken = $token;
    try {
      return $execute();
    }
    finally {
      $this->guiToken = NULL;
    }
  }

  /**
   * Protects update preparation that Drush performs before PRE_IMPORT fires.
   */
  public function updateMap(callable $update): void {
    if ($this->owner !== NULL) {
      $update();
      return;
    }
    if ($this->guiToken !== NULL) {
      $this->state->resetCache();
      $run = $this->state->get(BookplateBatch::RUN_KEY);
      if (!$run || $run['token'] !== $this->guiToken) {
        throw new MigrateException('The GUI bookplate migration reservation is no longer active.');
      }
      $update();
      return;
    }
    if (!$this->lock->acquire(BookplateBatch::LOCK, 86400)) {
      throw new MigrateException('Another bookplate migration request is running.');
    }
    try {
      $this->state->resetCache();
      if ($this->state->get(BookplateBatch::RUN_KEY)) {
        throw new MigrateException('A GUI bookplate migration is reserved. Map updates from another entry point are not allowed.');
      }
      $update();
    }
    finally {
      $this->lock->release(BookplateBatch::LOCK);
    }
  }

  /**
   * Rejects unreserved execution before core changes migration status or data.
   */
  public function before(MigrateImportEvent|MigrateRollbackEvent $event): void {
    $migration = $event->getMigration();
    if (!in_array($migration->id(), BookplateBatch::IDS, TRUE)) {
      return;
    }
    if ($this->owner !== NULL) {
      throw new MigrateException('Another bookplate migration is already executing in this request.');
    }
    if ($this->guiToken !== NULL) {
      $this->state->resetCache();
      $run = $this->state->get(BookplateBatch::RUN_KEY);
      if (!$run || $run['token'] !== $this->guiToken) {
        throw new MigrateException('The GUI bookplate migration reservation is no longer active.');
      }
      return;
    }
    // CLI runs may exceed a web request's timeout. Early failures retain this
    // request-scoped lock until shutdown when core does not emit a POST event.
    if (!$this->lock->acquire(BookplateBatch::LOCK, 86400)) {
      throw new MigrateException('Another bookplate migration request is running.');
    }
    try {
      $this->state->resetCache();
      if ($this->state->get(BookplateBatch::RUN_KEY)) {
        throw new MigrateException('A GUI bookplate migration is reserved. Finish or reset the abandoned GUI run before using another entry point.');
      }
      $this->owner = $migration;
    }
    catch (MigrateException $e) {
      $this->lock->release(BookplateBatch::LOCK);
      throw $e;
    }
  }

  /**
   * Releases only the lock owned by this non-GUI migration execution.
   */
  public function after(MigrateImportEvent|MigrateRollbackEvent $event): void {
    if ($this->owner === $event->getMigration()) {
      $this->owner = NULL;
      $this->lock->release(BookplateBatch::LOCK);
    }
  }

}
