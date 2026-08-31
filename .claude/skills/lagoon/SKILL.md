---
name: lagoon
description: Operate the simplytest.me Lagoon hosting on amazee.io — check deployments, build logs, environments, service logs, backups, and Drush tasks. Use whenever the user asks about deploys, "is it live", build failures, production logs, database dumps, or PR environments, even if they don't say "Lagoon".
---

# Lagoon operations for simplytest.me

## Project context

- **API project name**: `simplytest` — NOT `simplytestme-website`. The `project:` key in `.lagoon.yml` does not match the API; always pass `-p simplytest`.
- **Lagoon instance**: `amazeeio` (default context)
- **Production environment**: `main` → https://simplytest.me, deploy target `us2.amazee.io`
- Every merge to `main` on GitHub auto-deploys production. Lagoon also builds an ephemeral environment per open PR (`pr-NNN`).

## Authentication

Run `lagoon login` first — it silently refreshes the token if already authenticated.

## Common workflows

Check recent deploys (statuses: `complete`, `failed`, `running`, `queued`, `cancelled`):

```bash
lagoon list deployments -p simplytest -e main --wide
```

Get a build log (the source of truth for `deployCompletedWithWarnings` — grep it for `Warning` and `WithWarnings` step markers):

```bash
lagoon get deployment -p simplytest -e main -N lagoon-build-XXXXX -L
```

List environments (watch for stale `pr-NNN` environments whose PRs are closed):

```bash
lagoon list environments -p simplytest
```

Service logs (`nginx`, `php`, `cli`, `mariadb`, `varnish`):

```bash
lagoon logs -p simplytest -e main -s php -n 100
```

Drush on production:

```bash
lagoon run custom -p simplytest -e main --command "drush <command>"
```

Trigger a deploy: `lagoon deploy latest -p simplytest -e main`

SSH: `lagoon ssh -p simplytest -e main`

## Backups (list → retrieve → download)

```bash
lagoon list backups -p simplytest -e main --output-json --pretty
lagoon retrieve backup -p simplytest -e main -B <backupId>   # stage it; takes ~1 min
lagoon get backup -p simplytest -e main -B <backupId>        # signed URL, ~5 min validity
```

Find the latest entry with the right `source` (`mariadb` or `files`); if `restore.status` is not `"success"`, retrieve and poll `list backups` until it is.

## Destructive operations

`lagoon delete environment -p simplytest -e pr-NNN --force` removes a stale PR environment. Sandboxed sessions may be unable to run deletes — hand the exact command to the user instead of retrying.
