# Enable Pathauto

## Action behavior

- **Enable automatic URL alias** sets the existing computed path item's
  `pathauto` property to `PathautoState::CREATE` for every translation and saves
  the node. Normal Pathauto save hooks persist the checkbox and generate aliases,
  subject to the site's pattern and alias-update settings. This is the equivalent
  of enabling the checkbox and saving, not an alias-only operation.
- **Update URL alias of an entity** uses the same generator operation as the
  Pathauto bulk menu for each translation and invalidates affected entity cache
  tags. It respects the saved automatic-alias state, patterns, default-revision
  checks, and alias-update settings. It does not save content or enable the
  checkbox. Enable automatic aliases first for nodes that have opted out.

The module replaces the class of the existing `pathauto_update_alias` action
through `hook_action_info_alter()`. Existing Views Bulk Operations selections and
configured actions retain their IDs; configuration changes are not required.
This override applies to all entity types using that action, not just equipment.
Rebuild Drupal caches after deploying the module changes (`drush cr`).

## Diagnosis

Pathauto's upstream update action sets `pathauto = CREATE` in memory, then calls
the generator without saving the entity or persisting the state. Consequently it
can replace a manual alias while the node form's checkbox remains unchecked.
The Pathauto bulk menu does not force that flag on. The enable action's normal
node save does persist it; that behavior was verified on equipment nodes.

The path field already computes its first item even when there is no alias.
The enable action modifies that item directly rather than attempting to append
another item based on field emptiness.

## Regression Check

On a local site with this module enabled and the equipment content type and
pattern installed, run from the project root:

```sh
ddev drush cr
ddev drush php:script web/modules/custom/enable_pathauto/tests/action-regression.php
```

The script reproduces upstream behavior, checks both registered actions against
real equipment nodes with and without manual aliases, checks persistence after
reload, compares regeneration with the bulk generator, and checks cache
invalidation and unchanged content/revisions. Fixture database changes are
rolled back. Run only on a development site: entity-save integrations can have
effects outside the database transaction.
