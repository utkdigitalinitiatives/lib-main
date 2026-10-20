<?php

declare(strict_types=1);

namespace Drupal\enable_pathauto\Plugin\Action;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\pathauto\Plugin\Action\UpdateAction;

final class BulkUpdateAlias extends UpdateAction
{

    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        if (!$entity instanceof ContentEntityInterface || !$entity->hasField('path')) {
            return;
        }

        $cache_tags = [];
        foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
            $translation = $entity->getTranslation($langcode);
            $result = $this->pathautoGenerator->updateEntityAlias($translation, 'bulkupdate', ['message' => false]);
            if ($result) {
                $cache_tags = array_merge($cache_tags, $translation->getCacheTagsToInvalidate());
            }
        }

        Cache::invalidateTags(array_unique($cache_tags));
    }
}
