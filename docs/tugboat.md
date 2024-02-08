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
