# SimplyTest

## Managing configuration

The SimplyTest project leverages Drupal's ability to have a site's configuration
live within the distribution. When making configuration changes, be sure to run Drush
config export to export config to the `config/sync` directory.

## Time limit
Each SimplyTest sandbox lasts 2 hours, via [Tugboat setting](https://git.drupalcode.org/project/tugboat/-/blob/7ffc758424cc0d1ad6d1d4c41ce75f58f075bd5f/src/Cron.php#L32). The lifespan starts when
the sandbox is created, not when it becomes inactive.
