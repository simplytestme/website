---
name: checks
description: Run the local quality gates that mirror CI before pushing — PHPStan, Rector, PHPUnit with the 85% coverage gate. Use before opening a PR, when the user asks to "run the checks", or when diagnosing a red CI run on GitHub Actions.
---

# Local quality gates

CI (`.github/workflows/main.yml`) runs five jobs: `site:install`, `phpstan`, `rector`, `phpunit`, `cypress`. The first four have exact local equivalents; run them in this order (fastest feedback first):

```bash
php vendor/bin/phpstan.phar --memory-limit=1G   # 1G is required locally; default 128M crashes workers
vendor/bin/rector --dry-run                      # CI runs without --dry-run but must produce no diff
composer tests                                   # PHPUnit, no coverage — fast iteration
composer test:coverage                           # the real gate: coverage report + 85% threshold
```

Run a single test file while iterating:

```bash
php vendor/bin/phpunit web/modules/custom/<module>/tests/src/Kernel/<Test>.php
```

## Rules

- PHPStan is level 6 + `bleedingEdge` with `phpstan-baseline.neon`. Fix new findings with real types (array shapes, explicit `?Type` nullability); never grow the baseline for new code.
- The coverage gate (`scripts/coverage-check.php`) fails below 85% line coverage on `web/modules/custom`. New code needs Kernel or Unit tests — mock HTTP through `MockedHttpMiddleware` in `simplytest_projects_test`, never the network.
- Local PHP may be newer than CI's (CI pins PHP 8.3). A deprecation PHPStan flags locally but CI misses is still worth fixing — it fails CI on the next runner bump.

## Debugging a red CI run

```bash
gh run list --branch <branch> --limit 5
gh run view <run-id> --log-failed
gh pr checks <pr-number> --watch --interval 30
```

Cypress has no quick local equivalent — it needs a full site install. Check the uploaded `cypress` artifact (screenshots/videos) on the failed run first, and `drush watchdog:show` output in the job log.
