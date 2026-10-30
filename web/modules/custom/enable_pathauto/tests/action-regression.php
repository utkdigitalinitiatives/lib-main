<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\enable_pathauto\Plugin\Action\BulkUpdateAlias;
use Drupal\node\NodeInterface;
use Drupal\pathauto\PathautoState;
use Drupal\pathauto\Plugin\Action\UpdateAction;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
};

$manager = \Drupal::service('plugin.manager.action');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$generator = \Drupal::service('pathauto.generator');
$enable = $manager->createInstance('enable_pathauto_enable_automatic_alias');
$update = $manager->createInstance('pathauto_update_alias');
$check($update instanceof BulkUpdateAlias, 'Existing update action uses the bulk-compatible override.');
$invalidator = new class implements CacheTagsInvalidatorInterface {
    public array $tags = [];

    public function invalidateTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }
};
\Drupal::service('cache_tags.invalidator')->addInvalidator($invalidator);
$transaction = \Drupal::database()->startTransaction();
$nodes = [];

try {
    $suffix = bin2hex(random_bytes(6));
    $reload = static function (NodeInterface $node) use ($storage): NodeInterface {
        $storage->resetCache([$node->id()]);
        $loaded = $storage->load($node->id());
        assert($loaded instanceof NodeInterface);
        return $loaded;
    };
    $create = static function (?string $alias) use ($storage, $suffix, &$nodes): NodeInterface {
        $node = $storage->create([
            'type' => 'equipment',
            'title' => 'Pathauto regression ' . $suffix . ' ' . count($nodes),
            'path' => [['alias' => $alias, 'pathauto' => PathautoState::SKIP]],
        ]);
        assert($node instanceof NodeInterface);
        $node->save();
        $nodes[] = $node;
        return $node;
    };

    $node = $reload($create('/manual-' . $suffix));
    $upstream = UpdateAction::create(\Drupal::getContainer(), [], 'pathauto_update_alias', $manager->getDefinition('pathauto_update_alias'));
    $upstream->execute($node);
    $node = $reload($node);
    $check(
        $node->path->pathauto === PathautoState::SKIP && $node->path->alias !== '/manual-' . $suffix,
        'Upstream action reproduces alias replacement with the persisted checkbox still off.'
    );

    foreach ([null, '/preserved-' . $suffix] as $alias) {
        $node = $reload($create($alias));
        $update->execute($node);
        $node = $reload($node);
        $check(
            $node->path->pathauto === PathautoState::SKIP && $node->path->alias === $alias,
            'Bulk-compatible update preserves disabled state and existing alias.'
        );

        $enable->execute($node);
        $node = $reload($node);
        $check(
            $node->path->pathauto === PathautoState::CREATE
                && \Drupal::keyValue('pathauto_state.node')->get($node->id()) === PathautoState::CREATE
                && count($node->path) === 1,
            'Enable persists the checkbox on the single path item after reload.'
        );
        $check(
            str_starts_with($node->path->alias ?? '', '/equipment/'),
            'Enable generates the equipment alias through normal node-save hooks.'
        );

        $enable->execute($node);
        $node = $reload($node);
        $check(
            $node->path->pathauto === PathautoState::CREATE && count($node->path) === 1,
            'Enabling an already-enabled node is safe.'
        );

        $title = $node->getTitle();
        $revision = $node->getRevisionId();
        $node->setTitle($title . ' updated');
        $cache_tags = $node->getCacheTagsToInvalidate();
        $invalidator->tags = [];
        $update->execute($node);
        $actual = \Drupal::service('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), $node->language()->getId());
        $check(
            $actual !== null && str_ends_with($actual['alias'], '-updated'),
            'Update regenerates an enabled alias from the current tokens.'
        );
        $check(array_diff($cache_tags, $invalidator->tags) === [], 'Updating an alias invalidates the node cache tags.');
        $generator->updateEntityAlias($node, 'bulkupdate', ['message' => false]);
        $expected = \Drupal::service('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), $node->language()->getId());
        $check($actual === $expected, 'Update matches the generator used by the Pathauto bulk menu.');
        $node = $reload($node);
        $check(
            $node->getTitle() === $title && $node->getRevisionId() === $revision
                && $node->path->pathauto === PathautoState::CREATE,
            'Alias update does not save content, create revisions, or change persisted state.'
        );
    }

    $enable->execute(null);
    $update->execute(null);
    $check(true, 'Both actions safely ignore missing entities.');
} finally {
    try {
        $storage->delete($nodes);
    } finally {
        $transaction->rollBack();
        $storage->resetCache();
        $alias_storage->resetCache();
        \Drupal::service('path_alias.manager')->cacheClear();
    }
}

echo "All action regression checks passed; fixture database changes rolled back.\n";
