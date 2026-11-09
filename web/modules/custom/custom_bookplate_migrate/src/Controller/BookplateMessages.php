<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Link;
use Drupal\custom_bookplate_migrate\BookplateBatch;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Displays paged messages for discovered migrations, without config entities.
 */
final class BookplateMessages extends ControllerBase {

  public function __construct(
    protected MigrationPluginManagerInterface $migrations,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('database'),
    );
  }

  /**
   * Shows failures and duplicate skips, with their source and destination IDs.
   */
  public function overview(string $migration_id): array {
    if (!in_array($migration_id, BookplateBatch::IDS, TRUE)) {
      throw new NotFoundHttpException();
    }
    $migration = $this->migrations->createInstance($migration_id);
    $map = $migration->getIdMap();
    // Initialize map/message tables through the migration API.
    $map->getMessages();
    $query = $this->database->select('migrate_message_' . $migration_id, 'msg');
    $query->leftJoin('migrate_map_' . $migration_id, 'map', 'map.source_ids_hash = msg.source_ids_hash');
    $query->fields('msg', ['level', 'message']);
    $query->fields('map', ['sourceid1', 'destid1']);
    $query->orderBy('msg.msgid', 'DESC');
    $pager = $query->extend(PagerSelectExtender::class);
    if (!$pager instanceof PagerSelectExtender) {
      throw new \LogicException('The migration message pager could not be initialized.');
    }
    $messages = $pager->limit(50)->execute();
    $rows = [];
    foreach ($messages as $message) {
      $rows[] = [
        $message->sourceid1 ?? '',
        $message->destid1 ?? '',
        $message->level,
        ['data' => ['#plain_text' => $message->message]],
      ];
    }
    return [
      'back' => Link::createFromRoute($this->t('Back to Bookplate migration'), 'custom_bookplate_migrate.run')->toRenderable(),
      'messages' => [
        '#type' => 'table',
        '#caption' => $this->t('@migration messages across all years; newest first', ['@migration' => $migration->label()]),
        '#header' => [$this->t('Source ID'), $this->t('Destination ID'), $this->t('Level'), $this->t('Message')],
        '#rows' => $rows,
        '#empty' => $this->t('No migration messages.'),
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => ['max-age' => 0],
    ];
  }

}
