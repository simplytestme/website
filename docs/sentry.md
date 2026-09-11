# Sentry

Errors are reported to Sentry so somebody finds out about them without reading
logs. `simplytest_sentry` is the whole integration: one logger service, tagged
`logger`, that forwards Drupal log messages at error level and above.

## What it sends

Only `error` and worse. Everything a sandbox does wrong on the way in is a
warning or a notice, and `lagoon_logs` already ships all of it to Lagoon;
Sentry is for the handful of things somebody has to go and fix.

Each event carries:

- the message with its placeholders filled in, as the title,
- a `channel` tag, which is what to write alert rules against —
  `simplytest_tugboat` covers launches and the base previews,
- the scalar parts of the log context, including the placeholder values, as
  extra data,
- a fingerprint of the channel and the message *before* substitution.

The fingerprint is the part worth knowing about. Sentry groups on what it is
given, and these messages carry the values that made them: a base preview name,
an age, a project. Grouping on the message template instead means one issue per
thing that is wrong, rather than a new one each time it is reported.

An exception in the context is sent as an exception, so Sentry builds a real
trace from it, and only the rendered message goes along as extra data. The
exception object itself is never serialized: it holds whatever it was thrown
with, and an HTTP client error drags in the request, the response, and the
handler stack behind it.

## Configuration

There is none, and no DSN in `config/sync`. The hub is built from the
environment:

| Variable | Falls back to | Effect |
| --- | --- | --- |
| `SENTRY_DSN` | — | Without it nothing is reported at all |
| `SENTRY_ENVIRONMENT` | `LAGOON_ENVIRONMENT` | Which environment an issue came from |
| `SENTRY_RELEASE` | `LAGOON_GIT_SHA` | Which deploy an issue came from |

The variables are set on the production environment only, which is what keeps
pull request environments and local installs out of the project:

```bash
lagoon add variable -p simplytest -e main -N SENTRY_DSN -V "https://…" -S runtime
```

Sentry's own error and exception handlers are removed at build time. Drupal
logs PHP errors and uncaught exceptions itself and the logger sends those on,
so leaving them installed would open a second issue for one problem. The fatal
handler stays: a request that runs out of memory never reaches the logger, and
that is the failure nobody finds out about otherwise.
