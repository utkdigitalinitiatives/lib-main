<?php

/**
 * @file
 * Runs a reversible 2025 migration trial and synthetic regression checks.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Extension\Extension;
use Drupal\custom_bookplate_migrate\BookplateIdentity;
use Drupal\custom_bookplate_migrate\BookplateBatch;
use Drupal\custom_bookplate_migrate\Controller\BookplateMessages;
use Drupal\custom_bookplate_migrate\Form\BookplateMigrationForm;
use Drupal\custom_bookplate_migrate\EventSubscriber\BookplateExecutionGuard;
use Drupal\Core\Form\FormState;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;
use Drupal\migrate_tools\MigrateExecutable as ToolsMigrateExecutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  echo "PASS: $message\n";
};

$database = Database::getConnection();
$check($database->driver() === 'pgsql', 'Trial destination supports transactional schema changes.');
foreach (['migrate', 'migrate_plus', 'migrate_tools'] as $dependency) {
  $check(\Drupal::moduleHandler()->moduleExists($dependency), "$dependency is enabled.");
}
$ids = ['bookplate_files', 'bookplate_images', 'bookplate_nodes'];
foreach ($ids as $id) {
  $check(!$database->schema()->tableExists('migrate_map_' . $id), "$id has no previous migration map to disturb.");
}

$module = 'custom_bookplate_migrate';
$path = 'modules/custom/' . $module;
$handler = \Drupal::moduleHandler();
$original_modules = $handler->getModuleList();
$handler->setModuleList($original_modules + [$module => new Extension(DRUPAL_ROOT, 'module', $path . '/' . $module . '.info.yml')]);
\Drupal::service('container.namespaces')['Drupal\\' . $module] = DRUPAL_ROOT . '/' . $path . '/src';
\Drupal::service('class_loader')->addPsr4('Drupal\\' . $module . '\\', DRUPAL_ROOT . '/' . $path . '/src');
foreach ([
  'plugin.manager.migrate.source', 'plugin.manager.migrate.process',
  'plugin.manager.migrate.id_map', 'plugin.manager.migration',
] as $service) {
  \Drupal::service($service)->clearCachedDefinitions();
}

$transaction = $database->startTransaction();
$manager = \Drupal::service('plugin.manager.migration');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$file_storage = \Drupal::entityTypeManager()->getStorage('file');
$http_client = \Drupal::httpClient();
$registered_guard = \Drupal::hasService('custom_bookplate_migrate.execution_guard');
$guard = $registered_guard
  ? \Drupal::service('custom_bookplate_migrate.execution_guard')
  : new BookplateExecutionGuard(\Drupal::lock(), \Drupal::state());
if (!$registered_guard) {
  \Drupal::getContainer()->set('custom_bookplate_migrate.execution_guard', $guard);
  \Drupal::service('event_dispatcher')->addSubscriber($guard);
}
$migrations = [];
$directories = [];
$run = static function (MigrationInterface $migration): MigrateExecutable {
  $executable = new MigrateExecutable($migration, new MigrateMessage());
  if ($executable->import() !== MigrationInterface::RESULT_COMPLETED) {
    throw new RuntimeException('Migration did not complete: ' . $migration->id());
  }
  return $executable;
};
$run_batch = static function (string $operation, int $year, bool $retry = FALSE) use ($check): array {
  require_once DRUPAL_ROOT . '/core/includes/form.inc';
  BookplateBatch::start($operation, $year, $retry);
  $token = \Drupal::state()->get(BookplateBatch::RUN_KEY)['token'];
  \Drupal::state()->set('custom_bookplate_migrate.award_year', $year + 1);
  foreach (['core', 'drush'] as $entry_point) {
    foreach (['import', 'rollback'] as $attempt) {
      $migration = \Drupal::service('plugin.manager.migration')->createInstance('bookplate_nodes');
      $executable = $entry_point === 'core'
        ? new MigrateExecutable($migration, new MigrateMessage())
        : new ToolsMigrateExecutable($migration, new MigrateMessage(), \Drupal::service('keyvalue'), \Drupal::time(), \Drupal::service('string_translation'));
      $before = $migration->getIdMap()->processedCount();
      try {
        $executable->$attempt();
        throw new RuntimeException('Non-GUI execution unexpectedly accepted during a GUI reservation.');
      }
      catch (MigrateException $e) {
        $check(str_contains($e->getMessage(), 'reserved'), "$entry_point $attempt is rejected while the GUI reservation exists.");
      }
      $map = \Drupal::service('plugin.manager.migration')->createInstance('bookplate_nodes')->getIdMap();
      foreach (['prepareUpdate', 'setUpdate'] as $update) {
        try {
          $update === 'prepareUpdate' ? $map->prepareUpdate() : $map->setUpdate(['nid' => 1]);
          throw new RuntimeException('Pre-import map update unexpectedly accepted during a GUI reservation.');
        }
        catch (MigrateException $e) {
          $check(str_contains($e->getMessage(), 'reserved'), "Drush $update is guarded before the import event.");
        }
      }
      $check($migration->getStatus() === MigrationInterface::STATUS_IDLE && $migration->getIdMap()->processedCount() === $before, 'Rejected execution leaves status and maps unchanged.');
    }
  }
  try {
    BookplateBatch::start('import', $year);
    throw new RuntimeException('Concurrent GUI batch unexpectedly accepted.');
  }
  catch (MigrateException $e) {
    $check(str_contains($e->getMessage(), 'reserved'), 'Concurrent GUI runs are rejected.');
  }
  $results = [];
  foreach ($operation === 'rollback' ? array_reverse(BookplateBatch::IDS) : BookplateBatch::IDS as $id) {
    $context = ['sandbox' => [], 'results' => $results, 'finished' => 0];
    $iterations = 0;
    do {
      BookplateBatch::process($id, $operation, $year, $token, $retry, $context);
      if (++$iterations > 1000) {
        throw new RuntimeException('Batch failed to terminate.');
      }
    } while ($context['finished'] < 1);
    $results = $context['results'];
  }
  BookplateBatch::finished(TRUE, $results, []);
  \Drupal::state()->set('custom_bookplate_migrate.award_year', $year);
  $check(\Drupal::state()->get(BookplateBatch::RUN_KEY) === NULL, 'Finishing the GUI batch releases its reservation.');
  return $results;
};

try {
  $databases = [];
  include DRUPAL_ROOT . '/sites/default/settings.local.php';
  $check(isset($databases['migrate']['default']), 'Local settings define the legacy database connection.');
  $connection_info = Database::getConnectionInfo('migrate')['default'];
  foreach (['database', 'host', 'port', 'username', 'password'] as $key) {
    $check(($connection_info[$key] ?? NULL) === ($databases['migrate']['default'][$key] ?? NULL), "Effective migration connection preserves local $key setting.");
  }
  $test_source = getenv('BOOKPLATE_MIGRATE_TEST_DDEV_SOURCE');
  if ($test_source !== FALSE) {
    if (!preg_match('/^[a-z0-9][a-z0-9-]*$/D', $test_source)) {
      throw new RuntimeException('BOOKPLATE_MIGRATE_TEST_DDEV_SOURCE must be a DDEV project name.');
    }
    Database::removeConnection('migrate');
    Database::addConnectionInfo('migrate', 'default', [
      'driver' => 'mysql',
      'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
      'database' => 'db',
      'host' => 'ddev-' . $test_source . '-db',
      'port' => 3306,
      'username' => 'db',
      'password' => 'db',
      'prefix' => '',
    ]);
    echo "Using the local DDEV legacy snapshot for this test process only.\n";
  }
  $contender = new DatabaseLockBackend($database);
  foreach ([MigrateImportEvent::class, MigrateRollbackEvent::class] as $event_class) {
    $migration = $manager->createInstance('bookplate_nodes');
    $event = $event_class === MigrateImportEvent::class
      ? new MigrateImportEvent($migration, new MigrateMessage())
      : new MigrateRollbackEvent($migration);
    $guard->before($event);
    $check(!$contender->acquire(BookplateBatch::LOCK), 'Non-GUI execution holds the shared lock against other requests.');
    $guard->after($event);
    $check($contender->acquire(BookplateBatch::LOCK), 'Completion releases the non-GUI execution lock.');
    try {
      $guard->before($event);
      throw new RuntimeException('Execution unexpectedly accepted while another request holds the lock.');
    }
    catch (MigrateException $e) {
      $check(str_contains($e->getMessage(), 'running'), 'A competing request blocks non-GUI execution.');
    }
    finally {
      $contender->release(BookplateBatch::LOCK);
    }
  }
  \Drupal::state()->delete('custom_bookplate_migrate.award_year');
  $default_source = $manager->createInstance('bookplate_nodes')->getSourcePlugin();
  $check(in_array('2026', $default_source->query()->getArguments(), TRUE), 'Unconfigured year defaults to 2026.');
  \Drupal::state()->set('custom_bookplate_migrate.award_year', 'invalid');
  try {
    $manager->createInstance('bookplate_nodes')->getSourcePlugin()->query();
    throw new RuntimeException('Invalid year unexpectedly accepted.');
  }
  catch (MigrateException $e) {
    $check(str_contains($e->getMessage(), 'four-digit'), 'Invalid year is explicitly rejected.');
  }
  \Drupal::state()->set('custom_bookplate_migrate.award_year', 2025);
  $source_nodes = $manager->createInstance('bookplate_nodes')->getSourcePlugin()->query()->execute()->fetchAll(FetchAs::Associative);
  $check(count($source_nodes) > 0, 'Real 2025 bookplates are available for the trial.');
  $existing = 0;
  $preserved = [];
  foreach ($source_nodes as $source_node) {
    $matches = BookplateIdentity::findExisting($source_node['title'], $source_node['book_title']);
    if ($matches) {
      $existing++;
      foreach ($node_storage->loadMultiple($matches) as $node) {
        $preserved[$node->id()] = $node->toArray();
      }
    }
    else {
      $retry_nid ??= $source_node['nid'];
    }
  }
  $gui_form = BookplateMigrationForm::create(\Drupal::getContainer());
  $form_state = new FormState();
  $built = $gui_form->buildForm([], $form_state);
  $check($built['award_year']['#default_value'] === 2025, 'GUI defaults to the site-selected year.');
  $form_state->setValues(['operation' => 'rollback', 'award_year' => 2025, 'confirm' => FALSE]);
  $gui_form->validateForm($built, $form_state);
  $check($form_state->hasAnyErrors(), 'GUI rollback requires explicit confirmation.');
  $form_state->clearErrors();
  $form_state->setValues(['operation' => 'import', 'award_year' => 9999]);
  $gui_form->validateForm($built, $form_state);
  $check(isset($form_state->getErrors()['award_year']), 'GUI prevents empty-year imports.');
  $form_state->clearErrors();
  \Drupal::state()->set(BookplateBatch::RUN_KEY, [
    'token' => 'abandoned-fixture',
    'touched' => \Drupal::time()->getCurrentTime(),
  ]);
  $form_state->setValues(['operation' => 'reset', 'award_year' => 2025, 'confirm' => TRUE]);
  $gui_form->validateForm($built, $form_state);
  $check($form_state->hasAnyErrors(), 'GUI refuses to reset a recently active batch.');
  $form_state->clearErrors();
  \Drupal::state()->set(BookplateBatch::RUN_KEY, [
    'token' => 'abandoned-fixture',
    'touched' => \Drupal::time()->getCurrentTime() - 901,
  ]);
  $gui_form->validateForm($built, $form_state);
  $check(!$form_state->hasAnyErrors(), 'GUI permits a confirmed reset after 15 minutes without activity.');
  $gui_form->submitForm($built, $form_state);
  $check(\Drupal::state()->get(BookplateBatch::RUN_KEY) === NULL, 'GUI abandoned-run reset releases the reservation without deleting content.');
  try {
    $stale_context = ['sandbox' => [], 'results' => []];
    BookplateBatch::process('bookplate_nodes', 'import', 2025, 'abandoned-fixture', FALSE, $stale_context);
    throw new RuntimeException('An abandoned batch unexpectedly resumed.');
  }
  catch (MigrateException $e) {
    $check(str_contains($e->getMessage(), 'no longer active'), 'A reset invalidates the old batch token.');
  }
  if (isset($retry_nid)) {
    $failure_listener = static function (MigratePreRowSaveEvent $event) use ($retry_nid): void {
      if ($event->getMigration()->id() === 'bookplate_nodes' && $event->getRow()->getSourceProperty('nid') == $retry_nid) {
        throw new MigrateException('Intentional GUI regression failure; retry after removing this test listener.');
      }
    };
    \Drupal::service('event_dispatcher')->addListener(MigrateEvents::PRE_ROW_SAVE, $failure_listener);
    try {
      $failed_results = $run_batch('import', 2025);
      $check(!empty($failed_results['failed']), 'GUI reports row failures instead of reporting a successful import.');
    }
    finally {
      \Drupal::service('event_dispatcher')->removeListener(MigrateEvents::PRE_ROW_SAVE, $failure_listener);
    }
  }
  $batch_results = $run_batch('import', 2025, TRUE);
  $check(empty($batch_results['failed']), 'Real 2025 GUI import batch succeeds.');
  foreach ($ids as $id) {
    $migration = $manager->createInstance($id);
    $migrations[$id] = $migration;
    $check($migration->getIdMap()->errorCount() === 0, "$id completes the real 2025 trial without failed rows.");
  }
  $message_page = BookplateMessages::create(\Drupal::getContainer())->overview('bookplate_nodes');
  $check(count($message_page['messages']['#rows']) > 0 && count($message_page['messages']['#rows']) <= 50, 'GUI messages show paginated duplicate logs.');
  $node_map = $migrations['bookplate_nodes']->getIdMap();
  $check($node_map->importedCount() === count($source_nodes) - $existing, 'Only missing 2025 pairs are imported.');
  $ignored = $database->select('migrate_map_bookplate_nodes', 'm')
    ->condition('source_row_status', MigrateIdMapInterface::STATUS_IGNORED)->countQuery()->execute()->fetchField();
  $check((int) $ignored === $existing, 'All pre-existing 2025 pairs are skipped.');
  $check($node_map->messageCount() >= $existing, 'Skipped existing pairs have migration messages.');
  $node_storage->resetCache(array_keys($preserved));
  foreach ($node_storage->loadMultiple(array_keys($preserved)) as $node) {
    $check($node->toArray() === $preserved[$node->id()], 'An existing 2025 bookplate is unchanged.');
  }
  foreach ($source_nodes as $source_node) {
    $check(count(BookplateIdentity::findExisting($source_node['title'], $source_node['book_title'])) === 1, 'A 2025 pair exists exactly once after the trial.');
  }
  $before_rerun = $node_map->importedCount();
  $run_batch('import', 2025);
  $check($node_map->importedCount() === $before_rerun, 'Repeating the 2025 import creates no new nodes.');
  $check($node_map->messageCount() >= $existing, 'Repeating a GUI import preserves the existing duplicate-skip messages.');
  echo sprintf("2025 trial: %d source rows, %d preserved, %d imported.\n", count($source_nodes), $existing, $before_rerun);

  $fixture_id = 2000000001;
  $fixture = [
    'nid' => $fixture_id,
    'title' => 'Bookplate regression ' . bin2hex(random_bytes(6)),
    'book_title' => 'Fixture book A',
    'award_year' => '2025',
    'status' => 1,
    'created' => 1700000000,
    'changed' => 1700000001,
    'field_book_author' => ['value' => 'Fixture author'],
    'field_bp_department' => ['value' => 'Fixture department'],
    'field_position_role' => ['value' => 'Fixture role'],
    'field_occasion' => ['value' => '<p>Fixture occasion</p>'],
    'field_why_chosen' => ['value' => '<p>Fixture reason</p>'],
    'field_is_author' => ['value' => 1],
    'field_ebook' => ['value' => 0],
    'field_isbn' => ['value' => '9781234567890'],
    'field_link_to_record' => ['uri' => 'https://example.com/record', 'title' => 'Catalog'],
    'field_book_cover' => ['target_id' => $fixture_id],
    'field_honoree_photo' => ['target_id' => $fixture_id],
  ];
  $preexisting = $node_storage->create([
    'type' => 'bookplate',
    'title' => $fixture['title'],
    'field_book_title' => ['value' => $fixture['book_title'], 'format' => 'editor_html'],
    'status' => 0,
  ]);
  $preexisting->save();
  $preexisting = $node_storage->loadUnchanged($preexisting->id());
  $preexisting_values = $preexisting->toArray();

  \Drupal::service('class_loader')->addPsr4('Drupal\\sqlite\\', DRUPAL_ROOT . '/core/modules/sqlite/src');
  Database::addConnectionInfo('bookplate_fixture', 'default', [
    'driver' => 'sqlite',
    'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    'database' => ':memory:',
    'prefix' => '',
  ]);
  $source_database = Database::getConnection('default', 'bookplate_fixture');
  $common = "entity_id INTEGER, langcode TEXT DEFAULT 'en', deleted INTEGER DEFAULT 0, delta INTEGER DEFAULT 0";
  foreach ([
    'node_field_data' => 'nid INTEGER, title TEXT, type TEXT, status INTEGER, default_langcode INTEGER, langcode TEXT, created INTEGER, changed INTEGER',
    'taxonomy_term_field_data' => 'tid INTEGER, vid TEXT, name TEXT, default_langcode INTEGER',
    'node__field_award_year' => $common . ', field_award_year_target_id INTEGER',
    'node__field_book_title' => $common . ', field_book_title_value TEXT',
    'node__field_book_cover' => $common . ', field_book_cover_target_id INTEGER',
    'node__field_honoree_photo' => $common . ', field_honoree_photo_target_id INTEGER',
    'media_field_data' => 'mid INTEGER, name TEXT, bundle TEXT, status INTEGER, default_langcode INTEGER, langcode TEXT, created INTEGER, changed INTEGER',
    'media__field_media_image' => $common . ', field_media_image_target_id INTEGER, field_media_image_alt TEXT, field_media_image_title TEXT, field_media_image_width INTEGER, field_media_image_height INTEGER',
    'file_managed' => 'fid INTEGER, filename TEXT, uri TEXT, filemime TEXT, status INTEGER, created INTEGER, changed INTEGER',
  ] as $table => $columns) {
    $source_database->query('CREATE TABLE {' . $table . '} (' . $columns . ')');
  }
  foreach ([
    'field_book_author' => 'field_book_author_value TEXT, field_book_author_format TEXT',
    'field_bp_department' => 'field_bp_department_value TEXT, field_bp_department_format TEXT',
    'field_ebook' => 'field_ebook_value TEXT',
    'field_is_author' => 'field_is_author_value TEXT',
    'field_isbn' => 'field_isbn_value TEXT',
    'field_occasion' => 'field_occasion_value TEXT, field_occasion_format TEXT',
    'field_position_role' => 'field_position_role_value TEXT, field_position_role_format TEXT',
    'field_why_chosen' => 'field_why_chosen_value TEXT, field_why_chosen_format TEXT',
    'field_link_to_record' => 'field_link_to_record_uri TEXT, field_link_to_record_title TEXT, field_link_to_record_options TEXT',
  ] as $field => $columns) {
    $source_database->query('CREATE TABLE {node__' . $field . '} (' . $common . ', ' . $columns . ')');
  }
  $insert = static function (string $table, array $values) use ($source_database): void {
    $source_database->insert($table)->fields($values)->execute();
  };
  foreach ([2025, 2026] as $year) {
    $insert('taxonomy_term_field_data', [
      'tid' => $year,
      'vid' => 'bookplate_year',
      'name' => (string) $year,
      'default_langcode' => 1,
    ]);
  }
  foreach ([1, 2, 3, 4] as $nid) {
    $insert('node_field_data', [
      'nid' => $nid,
      'title' => $nid === 2 ? $fixture['title'] : 'Source fixture ' . $fixture['title'],
      'type' => 'bookplate',
      'status' => $nid === 4 ? 0 : 1,
      'default_langcode' => 1,
      'langcode' => 'en',
      'created' => 1700000000,
      'changed' => 1700000001,
    ]);
    $insert('node__field_award_year', ['entity_id' => $nid, 'field_award_year_target_id' => $nid === 3 ? 2026 : 2025]);
    $insert('node__field_book_title', ['entity_id' => $nid, 'field_book_title_value' => 'Fixture book A']);
    $insert('node__field_book_cover', [
      'entity_id' => $nid,
      'field_book_cover_target_id' => $nid === 1 ? 201 : $nid + 201,
    ]);
  }
  $insert('node__field_book_author', [
    'entity_id' => 1,
    'field_book_author_value' => 'Fixture author',
    'field_book_author_format' => 'editor_html',
  ]);
  $insert('node__field_link_to_record', [
    'entity_id' => 1,
    'field_link_to_record_uri' => 'https://example.com/fixture',
    'field_link_to_record_title' => 'Fixture catalog',
    'field_link_to_record_options' => '{}',
  ]);
  $insert('node__field_honoree_photo', ['entity_id' => 1, 'field_honoree_photo_target_id' => 202]);
  $insert('node_field_data', [
    'nid' => 1,
    'title' => 'Translation',
    'type' => 'bookplate',
    'status' => 1,
    'default_langcode' => 0,
    'langcode' => 'fr',
  ]);
  $insert('node__field_book_cover', ['entity_id' => 1, 'langcode' => 'fr', 'field_book_cover_target_id' => 206]);
  foreach (range(201, 206) as $mid) {
    $insert('media_field_data', [
      'mid' => $mid,
      'name' => 'Source image',
      'bundle' => 'image',
      'status' => 1,
      'default_langcode' => 1,
      'langcode' => 'en',
    ]);
    $insert('media__field_media_image', ['entity_id' => $mid, 'field_media_image_target_id' => $mid <= 202 ? 301 : 302]);
  }
  foreach ([301, 302] as $fid) {
    $insert('file_managed', [
      'fid' => $fid,
      'filename' => 'source.png',
      'uri' => 'public://source.png',
      'filemime' => 'image/png',
      'status' => 1,
    ]);
  }
  foreach ([
    'bookplate_files' => [301],
    'bookplate_images' => [201, 202],
    'bookplate_nodes' => [1, 2],
  ] as $id => $expected_ids) {
    $source = $manager->createInstance($id, ['source' => ['key' => 'bookplate_fixture']])->getSourcePlugin();
    $actual_ids = array_map('intval', $source->query()->execute()->fetchCol());
    $check($actual_ids === $expected_ids, "$id source filters year, publication, language, existing pairs, and shared assets correctly.");
    if ($id === 'bookplate_nodes') {
      $statement = $source->query()->execute();
      $statement->setFetchMode(FetchAs::Associative);
      $row = new Row($statement->fetch(), $source->getIds());
      $check($source->prepareRow($row), 'Bookplate source prepares joined field data.');
      $check($row->getSourceProperty('field_book_author') === ['value' => 'Fixture author', 'format' => 'editor_html'], 'Joined field rows preserve their value and format.');
      $check($row->getSourceProperty('field_link_to_record') === [
        'uri' => 'https://example.com/fixture',
        'title' => 'Fixture catalog',
        'options' => '{}',
      ], 'Joined link rows preserve all source properties.');
      $missing_row = new Row($statement->fetch(), $source->getIds());
      $check($source->prepareRow($missing_row) && $missing_row->getSourceProperty('field_book_author') === [], 'Missing joined fields remain empty arrays.');
    }
  }

  $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0ioAAAAASUVORK5CYII=');
  $mock = new MockHandler([new Response(200, ['Content-Type' => 'image/png'], $bytes)]);
  \Drupal::getContainer()->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));
  $fixture_rows = [
    'bookplate_files' => [
      [
        'fid' => $fixture_id,
        'filename' => 'regression image.png',
        'uri' => 'public://regression image.png',
        'filemime' => 'image/png',
        'status' => 1,
        'created' => 1700000000,
        'changed' => 1700000001,
      ],
    ],
    'bookplate_images' => [
      [
        'mid' => $fixture_id,
        'name' => 'Regression image',
        'image_target_id' => $fixture_id,
        'image_alt' => 'Fixture alt',
        'image_title' => 'Fixture title',
        'image_width' => 1,
        'image_height' => 1,
        'status' => 1,
        'created' => 1700000000,
        'changed' => 1700000001,
      ],
    ],
    'bookplate_nodes' => [
      $fixture,
      array_replace($fixture, ['nid' => $fixture_id + 1, 'book_title' => 'Fixture book B']),
      array_replace($fixture, ['nid' => $fixture_id + 2, 'title' => $fixture['title'] . ' other']),
      array_replace($fixture, ['nid' => $fixture_id + 3, 'book_title' => 'Fixture book B']),
      array_replace($fixture, ['nid' => $fixture_id + 4, 'book_title' => '']),
      array_replace($fixture, [
        'nid' => $fixture_id + 5,
        'book_title' => 'Broken image',
        'field_book_cover' => ['target_id' => $fixture_id + 99],
      ]),
      array_replace($fixture, [
        'nid' => $fixture_id + 6,
        'book_title' => 'No images',
        'field_book_cover' => [],
        'field_honoree_photo' => [],
      ]),
    ],
  ];
  foreach ($ids as $id) {
    $definition = $manager->getDefinition($id);
    $source_id = match ($id) {
      'bookplate_files' => 'fid',
      'bookplate_images' => 'mid',
      'bookplate_nodes' => 'nid',
    };
    $definition['source'] = [
      'plugin' => 'embedded_data',
      'data_rows' => $fixture_rows[$id],
      'ids' => [$source_id => ['type' => 'integer']],
    ];
    $definition['migration_dependencies'] = [];
    $migration = $manager->createStubMigration($definition);
    $run($migration);
    $migrations[$id] = $migration;
    if ($id === 'bookplate_files') {
      $file_id = $migration->getIdMap()->lookupDestinationIds([$fixture_id])[0][0];
      $imported_file = $file_storage->load($file_id);
      if (!$imported_file instanceof FileInterface) {
        throw new RuntimeException('Imported fixture file could not be loaded.');
      }
      $directories[] = dirname($imported_file->getFileUri());
    }
  }
  $fixture_map = $migrations['bookplate_nodes']->getIdMap();
  foreach ([0, 3] as $offset) {
    $check((int) $fixture_map->getRowBySource([$fixture_id + $offset])['source_row_status'] === MigrateIdMapInterface::STATUS_IGNORED, 'An existing or repeated fixture pair is skipped.');
  }
  foreach ([4, 5] as $offset) {
    $check((int) $fixture_map->getRowBySource([$fixture_id + $offset])['source_row_status'] === MigrateIdMapInterface::STATUS_FAILED, 'Unsafe identity or missing referenced image produces a failed row.');
  }
  $node_storage->resetCache([$preexisting->id()]);
  $check($node_storage->load($preexisting->id())->toArray() === $preexisting_values, 'An unpublished existing bookplate is preserved.');
  foreach ([1, 2, 6] as $offset) {
    $destination = $fixture_map->lookupDestinationIds([$fixture_id + $offset]);
    $check(count($destination) === 1, 'Distinct fixture pair imports successfully.');
  }
  $created = $node_storage->load($fixture_map->lookupDestinationIds([$fixture_id + 1])[0][0]);
  if (!$created instanceof NodeInterface) {
    throw new RuntimeException('Imported fixture bookplate could not be loaded.');
  }
  foreach ([
    'field_book_author', 'field_bp_department', 'field_position_role',
    'field_occasion', 'field_why_chosen', 'field_is_author', 'field_ebook',
    'field_isbn',
  ] as $field) {
    $check((string) $created->get($field)->value === (string) $fixture[$field]['value'], "$field is preserved.");
  }
  $check($created->field_book_title->format === 'editor_html', 'Formatted book title uses the destination format.');
  $check((int) $created->getCreatedTime() === $fixture['created'] && $created->getChangedTime() === $fixture['changed'], 'Node timestamps are preserved.');
  $check($created->field_link_to_record->uri === $fixture['field_link_to_record']['uri'], 'Catalog link is preserved.');
  $check($created->field_award_year->entity->label() === '2025', 'Award year resolves by term name, not legacy ID.');
  $image_id = $created->field_book_cover->target_id;
  $check($image_id && $created->field_honoree_photo->target_id === $image_id, 'Both image fields resolve to the migrated media.');
  $image = $media_storage->load($image_id);
  $check($image->field_media_image->alt === 'Fixture alt', 'Image alt text is preserved.');
  $file = $image->field_media_image->entity;
  $check(file_get_contents($file->getFileUri()) === $bytes, 'Downloaded file persists with the expected bytes.');
  $no_images = $node_storage->load($fixture_map->lookupDestinationIds([$fixture_id + 6])[0][0]);
  $check($no_images->field_book_cover->isEmpty() && $no_images->field_honoree_photo->isEmpty(), 'Absent optional images do not fail import.');
  $process = \Drupal::service('plugin.manager.migrate.process')->createInstance('bookplate_unique');
  try {
    $process->transform([$created->label(), $created->field_book_title->value], new MigrateExecutable($migrations['bookplate_nodes'], new MigrateMessage()), new Row(['nid' => $fixture_id + 1], ['nid' => ['type' => 'integer']]), 'title');
    throw new RuntimeException('Previously imported pair unexpectedly accepted.');
  }
  catch (MigrateSkipRowException $e) {
    $check(str_contains($e->getMessage(), 'preserved'), 'Previously imported pairs are protected even when reprocessed with --update.');
  }
  $download = \Drupal::service('plugin.manager.migrate.process')->createInstance('bookplate_download');
  $executable = new MigrateExecutable($migrations['bookplate_files'], new MigrateMessage());
  $download_row = new Row(['fid' => $fixture_id], ['fid' => ['type' => 'integer']]);
  foreach ([
    ['private://unsupported.png', NULL, 'require a legacy public://'],
    ['public://failure.png', new Response(500), 'download failed'],
    ['public://empty.png', new Response(200, [], ''), 'file is empty'],
  ] as [$uri, $response, $expected_message]) {
    if ($response !== NULL) {
      $mock->append($response);
    }
    try {
      $download->transform($uri, $executable, $download_row, 'uri');
      throw new RuntimeException('Invalid download unexpectedly succeeded.');
    }
    catch (MigrateException $e) {
      $check(str_contains($e->getMessage(), $expected_message), 'Unsupported, failed, or empty downloads report explicit errors.');
    }
  }
  $file_map = $manager->createInstance('bookplate_files')->getIdMap();
  for ($file_map->rewind(); $file_map->valid(); $file_map->next()) {
    $destination = $file_map->currentDestination();
    if ($destination) {
      $imported_file = $file_storage->load(reset($destination));
      if ($imported_file instanceof FileInterface) {
        $directories[] = dirname($imported_file->getFileUri());
      }
    }
  }
  $batch_results = $run_batch('rollback', 2026);
  $check(empty($batch_results['failed']), 'GUI rollback succeeds even when the selected year differs from imported rows.');
  foreach ($ids as $id) {
    $check($manager->createInstance($id)->getIdMap()->processedCount() === 0, 'GUI rollback clears all mapped rows, including failed and skipped rows.');
  }
}
finally {
  try {
    \Drupal::getContainer()->set('http_client', $http_client);
    foreach (array_reverse($ids) as $id) {
      if (isset($migrations[$id])) {
        (new MigrateExecutable($migrations[$id], new MigrateMessage()))->rollback();
      }
    }
    if (isset($preexisting)) {
      $check($node_storage->loadUnchanged($preexisting->id()) !== NULL, 'Migration rollback preserves existing content.');
      $preexisting->delete();
    }
    foreach (array_unique($directories) as $directory) {
      \Drupal::service('file_system')->rmdir($directory);
    }
  }
  finally {
    $transaction->rollBack();
    $node_storage->resetCache();
    $media_storage->resetCache();
    $file_storage->resetCache();
    \Drupal::entityTypeManager()->getStorage('taxonomy_term')->resetCache();
    \Drupal::state()->resetCache();
    if (!$registered_guard) {
      \Drupal::service('event_dispatcher')->removeSubscriber($guard);
    }
    Database::removeConnection('bookplate_fixture');
    $handler->setModuleList($original_modules);
    foreach ([
      'plugin.manager.migrate.source', 'plugin.manager.migrate.process',
      'plugin.manager.migrate.id_map', 'plugin.manager.migration',
    ] as $service) {
      \Drupal::service($service)->clearCachedDefinitions();
    }
  }
}

echo "All bookplate regression checks passed; trial database changes rolled back.\n";
