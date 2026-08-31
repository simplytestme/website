# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The source for [simplytest.me](https://simplytest.me): a Drupal site that launches throwaway Drupal evaluation sandboxes on Tugboat. Users pick a project (module, theme, distribution, or core), a version, and optional extras; the site provisions a Tugboat preview that lives for 2 hours.

## Commands

```bash
composer install                # installs Drupal core, contrib, and scaffolding
ddev si                         # install the site locally (Drush site:install simplytest)

composer tests                  # PHPUnit for web/modules/custom
php vendor/bin/phpunit web/modules/custom/simplytest_projects/tests/src/Kernel/ProjectFetcherTest.php
                                # run a single test file
composer test:coverage          # PHPUnit + Clover report + 85% line-coverage gate
                                # (scripts/coverage-check.php enforces the threshold)

php vendor/bin/phpstan.phar --memory-limit=1G   # level 6 + phpstan-baseline.neon;
                                                # the default 128M crashes locally
vendor/bin/rector               # also enforced in CI

npm run build                   # build the theme (Laravel Mix + gulp postcss)
npm run cypress:run             # E2E tests; CI installs the site first and
                                # serves it with PHP's built-in server
```

CI (`.github/workflows/main.yml`) runs five jobs on every push and PR to `main`: `site:install`, `phpstan`, `rector`, `phpunit` (with the coverage gate), and `cypress`.

## Architecture

Custom code lives in four modules under `web/modules/custom/`, all in the `Simplytest` package:

- **simplytest_projects** — the data layer. Defines the `simplytest_project` entity (`SimplytestProject`) and fetches project metadata from Drupal.org: `ProjectFetcher` queries the REST API for project info, `ProjectVersionManager` and `CoreVersionManager` parse release-history XML into a custom versions table, `ProjectTypes` maps Drupal.org term names to types. Sandbox-vs-full detection, version storage, and the project autocomplete all live here.
- **simplytest_launch** — the submission form and launch endpoint. The user-facing form is a React app (see below); this module validates the submission (constraint violations surface as `UnprocessableHttpEntityException`, a 422) and hands it to the instance manager.
- **simplytest_tugboat** — provisions the actual sandbox through the Tugboat API and reports instance/preview state back to the frontend.
- **simplytest_ocd** — "one click demos": preconfigured launch links.

Kernel tests mock all HTTP traffic through `MockedHttpMiddleware` in the `simplytest_projects_test` support module (`web/modules/custom/simplytest_projects/tests/modules/`). Add new mocked Drupal.org responses there rather than letting tests hit the network. `BufferedLogger` in the same module captures log assertions.

The install profile is `web/profiles/simplytest`, a distribution with `config_install_path: '../config/sync'` — site configuration lives in `config/sync/` at the repo root. After config changes, export with `drush config:export`.

The frontend launch form is React 19 (`downshift` for autocomplete), living in the theme at `web/themes/simplytest_theme`, built with Laravel Mix plus a gulp postcss pass. `npm run build` compiles both.

## Quality gates

- PHPStan runs at level 6 with `bleedingEdge` and a baseline. Fix new errors properly (typed array shapes, explicit nullability) instead of adding baseline entries.
- PHPUnit line coverage on `web/modules/custom` must stay at or above 85%.
- All custom-module tests are Unit or Kernel tests. Keep it that way — don't add Functional tests when a Kernel test can cover the behavior.

## Hosting and deploys

Hosted on amazee.io Lagoon. The API project name is `simplytest` (not the `simplytestme-website` in `.lagoon.yml` — that field is not what the CLI wants), production environment `main`, deploy target `us2.amazee.io`. Every merge to `main` auto-deploys to https://simplytest.me; Lagoon also builds ephemeral environments per PR. Use `lagoon` CLI commands with `-p simplytest -e main`.
