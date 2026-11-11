<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\custom_bookplate_migrate\BookplateBatch;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides browser-only controls for the discovered bookplate migrations.
 */
final class BookplateMigrationForm extends FormBase {

  public function __construct(
    protected MigrationPluginManagerInterface $migrations,
    protected StateInterface $state,
    protected LockBackendInterface $lock,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('state'),
      $container->get('lock'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'custom_bookplate_migrate_run';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => $this->t('Back up the database and files first. Import runs files, image media, then nodes. Existing honoree/book-title pairs are skipped and logged. Run only one migration at a time, including command-line imports.'),
    ];
    $form['award_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Award year'),
      '#default_value' => $this->state->get('custom_bookplate_migrate.award_year', 2026),
      '#min' => 1000,
      '#max' => 9999,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Only published source bookplates for this year are imported. The year is fixed for the duration of a batch.'),
    ];
    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operation'),
      '#options' => [
        'import' => $this->t('Import selected year'),
        'rollback' => $this->t('Roll back ALL bookplate migration years'),
        'reset' => $this->t('Reset an abandoned run (no content is deleted)'),
      ],
      '#default_value' => 'import',
      '#required' => TRUE,
    ];
    $form['retry_failed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retry failed rows for the selected year'),
      '#description' => $this->t('Use after fixing a reported problem. Successfully imported and skipped rows are not marked for update.'),
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('For rollback/reset: I confirm the operation and have read the warnings below.'),
      '#description' => $this->t('Rollback deletes all nodes, media, and files created by these migrations across ALL years, but not skipped existing content or year terms. Reset is only for a run with no active browser or CLI worker; wait at least 15 minutes after its last activity.'),
    ];
    $run = $this->state->get(BookplateBatch::RUN_KEY);
    if ($run) {
      $form['active'] = [
        '#markup' => $this->t('A GUI batch is reserved. Resume its progress page. If it has been abandoned for at least 15 minutes, use Reset before starting another run.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }
    $rows = [];
    foreach (BookplateBatch::IDS as $id) {
      $migration = $this->migrations->createInstance($id);
      $map = $migration->getIdMap();
      $rows[] = [
        $migration->label(),
        $migration->getStatusLabel(),
        $map->processedCount(),
        $map->importedCount(),
        $map->errorCount(),
        Link::createFromRoute($this->t('Messages'), 'custom_bookplate_migrate.messages', ['migration_id' => $id]),
      ];
    }
    $form['status'] = [
      '#type' => 'table',
      '#caption' => $this->t('Migration map totals across ALL years, not just the selected year'),
      '#header' => [
        $this->t('Migration'), $this->t('Status'), $this->t('Processed'),
        $this->t('Imported'), $this->t('Failed'), $this->t('Messages'),
      ],
      '#rows' => $rows,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Execute')];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getValue('operation');
    if ($operation !== 'import' && !$form_state->getValue('confirm')) {
      $form_state->setErrorByName('confirm', $this->t('Confirm the rollback/reset warnings before continuing.'));
    }
    $run = $this->state->get(BookplateBatch::RUN_KEY);
    if ($run && ($operation !== 'reset' || $run['touched'] > $this->time->getCurrentTime() - 900)) {
      $form_state->setErrorByName('operation', $this->t('A GUI batch is reserved. Resume it, or wait 15 minutes after its last activity before resetting an abandoned run.'));
    }
    if ($operation === 'import' && !$form_state->hasAnyErrors()) {
      try {
        $migration = $this->migrations->createInstance('bookplate_nodes', [
          'source' => ['selected_year' => (int) $form_state->getValue('award_year')],
        ]);
        if ($migration->getSourcePlugin()->count() === 0) {
          $form_state->setErrorByName('award_year', $this->t('No published source bookplates were found for this year. Nothing was imported.'));
        }
      }
      catch (MigrateException | DatabaseExceptionWrapper | \PDOException $e) {
        $this->getLogger('custom_bookplate_migrate')->error('Bookplate source check failed: @message', ['@message' => $e->getMessage()]);
        $form_state->setErrorByName('operation', $this->t('Cannot read the legacy bookplate database. Ask the site administrator to check the migrate connection and source schema; details were logged.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getValue('operation');
    if ($operation === 'reset') {
      $lock = $this->lock;
      if (!$lock->acquire(BookplateBatch::LOCK, 900)) {
        $this->messenger()->addError($this->t('A migration request is active. Do not reset it.'));
        return;
      }
      try {
        $this->state->resetCache();
        $run = $this->state->get(BookplateBatch::RUN_KEY);
        if ($run && $run['touched'] > $this->time->getCurrentTime() - 900) {
          $this->messenger()->addError($this->t('The batch has recent activity. Wait before resetting an abandoned run.'));
          return;
        }
        foreach (BookplateBatch::IDS as $id) {
          $this->migrations->createInstance($id)->setStatus(MigrationInterface::STATUS_IDLE);
        }
        $this->state->delete(BookplateBatch::RUN_KEY);
        $this->getLogger('custom_bookplate_migrate')->notice('An administrator reset the abandoned bookplate migration statuses.');
        $this->messenger()->addStatus($this->t('Migration statuses reset. No content was deleted. You may start a new import.'));
      }
      finally {
        $lock->release(BookplateBatch::LOCK);
      }
    }
    else {
      try {
        BookplateBatch::start($operation, (int) $form_state->getValue('award_year'), (bool) $form_state->getValue('retry_failed'));
      }
      catch (MigrateException $e) {
        $this->messenger()->addError($e->getMessage());
      }
    }
    $form_state->setRedirect('custom_bookplate_migrate.run');
  }

}
