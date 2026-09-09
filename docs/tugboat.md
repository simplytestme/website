# Tugboat

In order to test sandboxes locally, you need to have access to the Tugboat instance. These are managed by the maintainers.

Use configuration overrides in `web/sites/default/settings.local.php` to configure Tugboat.

```php
$config['tugboat.settings']['repository_id'] = '5c7aab3c14b2a10001a46d81';
$config['tugboat.settings']['repository_base'] = 'master';

$config['tugboat.settings']['token'] = 'TUGBOAT_TOKEN';
```

You may also copy `phpunit.xml.dist` to `phpunit.xml` to provide your Tugboat credentials for tests.

```xml
    <env name="TUGBOAT_API_KEY" value=''/>
    <env name="TUGBOAT_REPOSITORY_ID" value=''/>
```

This allows executing `InstanceManagerTest` for running sample builds via a test.

## Base previews

Every sandbox builds on a base preview: a preview that already ran the init
stage. There is one per supported core major (`base-drupal7` through
`base-drupal11`) and one per one click demo (`base-umami`, `base-commerce`).
The launch code finds them by name and picks the newest one that is ready to
build on.

A base has PHP extensions, Apache modules, and tooling installed. For Drupal 9
and later it also holds a complete `recommended-project` at the line's newest
release, in `stm`, which is where a sandbox builds. A sandbox that asks for
that release reuses the project and only adds what the launch needs, which
cuts the build and the snapshot Tugboat takes afterwards to about a third. Any
other release, including dev releases, builds from scratch as before. The
build log says which happened: look for "Reusing Drupal X from the base
preview".

The bases are created through the Tugboat API with generated config, not from
branches in the backing repository. On production, cron starts a fresh set once
a day and deletes a replaced base once no sandbox builds on it anymore. A build
that fails is deleted and retried on the next cycle, and launches keep using
the previous base in the meantime.

To inspect or rebuild them by hand:

```bash
drush simplytest:tugboat:base-previews:list      # what is on Tugboat, per base
drush simplytest:tugboat:base-previews:rebuild   # start a fresh set, or one: drush stbp-rebuild drupal11
drush simplytest:tugboat:base-previews:prune     # delete replaced and failed bases
```

Rebuilding is safe at any time: the old base stays in use until the new one is
ready.
