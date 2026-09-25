<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\AgentAccess;

final class AgentAccessConfigTest extends OneClickDemoConfigTestBase {

  protected static string $pluginId = 'oneclickdemo_agent_access';
  protected static string $pluginClass = AgentAccess::class;

  /**
   * @return array<string, mixed>
   */
  #[\Override]
  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:8.3-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'php -m | grep -qi bcmath || docker-php-ext-install bcmath',
            'a2enmod headers rewrite',
            'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
            'composer config --global policy.advisories.block false',
            'echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/my-php.ini',
            'rm -rf "${DOCROOT}"',
            'cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/cms:^2 stm --no-install',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            "cd \"\${TUGBOAT_ROOT}/stm\" && composer require --no-update 'drush/drush' 'drupal/tool:^1.0@beta' 'drupal/tool_belt:^1.0@alpha' 'drupal/mcp_server:^2.0.0-beta5@beta' 'drupal/mcp_server_tool_bridge:^1.0.0-beta3@beta' 'drupal/mcp_server_oauth-mcp_server_oauth:^1.0@alpha' 'drupal/agent_access:^1.0.0-alpha2@alpha'",
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/byte --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Agent Access Demo"',
            'cd "${DOCROOT}" && ../vendor/bin/drush recipe ../recipes/agent_access',
            'mkdir -p "${TUGBOAT_ROOT}/stm/oauth-keys" && cd "${DOCROOT}" && ../vendor/bin/drush simple-oauth:generate-keys "${TUGBOAT_ROOT}/stm/oauth-keys"',
            'chown -R www-data:www-data "${TUGBOAT_ROOT}/stm/oauth-keys"',
            'cd "${DOCROOT}" && ../vendor/bin/drush config:set simple_oauth.settings public_key "${TUGBOAT_ROOT}/stm/oauth-keys/public.key" -y',
            'cd "${DOCROOT}" && ../vendor/bin/drush config:set simple_oauth.settings private_key "${TUGBOAT_ROOT}/stm/oauth-keys/private.key" -y',
            'cd "${DOCROOT}" && ../vendor/bin/drush config:delete simple_oauth_server_metadata.settings registration_endpoint -y',
            'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
            'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
            'echo "SIMPLYEST_STAGE_FINALIZE"',
          ],
        ],
      ],
      'mysql' => [
        'image' => 'tugboatqa/mysql:8',
      ],
    ];
  }

}
