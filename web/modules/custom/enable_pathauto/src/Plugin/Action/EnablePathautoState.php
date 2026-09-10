<?php

declare(strict_types=1);

namespace Drupal\enable_pathauto\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\pathauto\PathautoState;

#[Action(
  id: 'enable_pathauto_enable_automatic_alias',
  label: new TranslatableMarkup('Enable automatic URL alias'),
  type: 'node',
  category: new TranslatableMarkup('Pathauto'),
)]
final class EnablePathautoState extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface || !$entity->hasField('path')) {
      return;
    }

    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);
      $path_field = $translation->get('path');

      // This is the value submitted by path[0][pathauto] on the node form.
      if ($path_field->isEmpty()) {
        $path_field->appendItem();
      }
      $path_field->first()->set('pathauto', PathautoState::CREATE);

      $translation->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object instanceof NodeInterface
      ? $object->access('update', $account, $return_as_object)
      : FALSE;
  }

}
