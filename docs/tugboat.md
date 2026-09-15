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

A one click demo's base is the whole demo, installed. Nothing about a demo
depends on the launch, so launching one clones the base's snapshot, which
Tugboat does in about ten seconds with nothing to build. When no usable base
exists the demo is built from scratch, which takes a few minutes.

The bases are created through the Tugboat API with generated config, not from
branches in the backing repository. On production, cron starts a fresh set once
a day and deletes a replaced base once no sandbox builds on it anymore. A build
that fails is deleted and retried on the next cycle, and launches keep using
the previous base in the meantime.

## How sandboxes expire

Every launch carries an `expires` timestamp, `sandbox_lifetime` from
`tugboat.settings` past the request. Tugboat deletes the preview itself when it
passes. Nothing else expires a sandbox, so a launch that goes out without it
stays on Tugboat until somebody deletes it by hand.

The Tugboat module ships a cron that did this instead, by listing every preview
in the repository and deleting the ones past the lifetime that Tugboat does not
report as an anchor. `BasePreviewCron` removes it with `#[RemoveHook]`. A base
preview is not an anchor: an anchor is the repository's default base, and these
are named previews picked per launch. So two hours into a base's day that sweep
started trying to delete it. Tugboat refused with a 409 while a sandbox was
built on the base, and let it through the rest of the time. That is why the
demo bases went first. A demo launch is a clone, and Tugboat counts a clone
separately from a base's children, so a demo base is never anything's parent.
The bases lived a few hours of their day and everything built from scratch for
the rest of it.

That cron also had no production guard, so any PR environment or local install
holding the token swept production.

## Knowing when one breaks

The daily rebuild is the only thing that runs a one click demo's install
commands end to end, which makes it the smoke test for them. Every cron run
reports on the bases first, before the pruner deletes the evidence, and logs an
error on the `simplytest_tugboat` channel when a base is:

- **failed** — the newest build failed, and launches are running on the one
  before it,
- **missing** — nothing carrying the name can be built on, so launches build
  from scratch and fail the way the build did,
- **stale** — no newer base has replaced it in two rebuild cycles, so the
  rebuild has stopped finishing.

A base is reported once per rebuild cycle, unless what is wrong with it
changes, so a base that stays broken does not fill the log. Tugboat itself
cannot report this: it has no outbound notification for a failed build, and the
bases are not built from pull requests, so there is no provider status to fail.

To inspect or rebuild them by hand:

```bash
drush simplytest:tugboat:base-previews:list      # what is on Tugboat, per base
drush simplytest:tugboat:base-previews:rebuild   # start a fresh set, or one: drush stbp-rebuild drupal11
drush simplytest:tugboat:base-previews:prune     # delete replaced and failed bases
```

Rebuilding is safe at any time: the old base stays in use until the new one is
ready.
