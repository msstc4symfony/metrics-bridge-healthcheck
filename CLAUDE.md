# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Symfony bundle (`msstc4symfony/metrics-bridge-healthcheck`, namespace `Msstc4Symfony\MetricsBridgeHealthcheck`, extension
alias `msstc4symfony_metrics_bridge_healthcheck`, no configuration tree) that connects `msstc4symfony/healthcheck-bundle`
to `msstc4symfony/metrics-bundle`: every probe run increments `healthcheck_checker_run{checker,probe,status}` and
`healthcheck_probe_run{probe,status}` and observes `healthcheck_checker_duration_histogram_seconds{checker,probe}`.
PHP >= 8.2, Symfony 7.4 / 8.x.

## Common commands

Develop against the CI profile: `COMPOSER=composer-ci.json composer install` (needs ext-redis).

- `make check` — `php -l`, PHPStan level 10, PHP-CS-Fixer, `composer validate --strict`,
  `composer audit`, Rector dry-run, deptrac. Run as `COMPOSER=composer-ci.json make check`.
- `make test` — unit + integration suites; `make infection`, `make fix`.
- `php ../bundle-standard/bin/verify-standard.php .` — standard compliance (the same check CI runs).

## Architecture in 60 seconds

- `Infrastructure\Metrics\HealthcheckMetric` — the single declaration of the three metrics (names, labels, buckets);
  `CheckerRunStatus` / `ProbeRunStatus` — the `status` label values. Counters on purpose: a gauge in shared storage
  holds the last replica's value.
- `Infrastructure\Metrics\HealthcheckMetricsListener` (`@internal`) — `#[AsEventListener]` on healthcheck 2.2's
  `HealthCheckerCompletedEvent` and `HealthCheckRunCompletedEvent`; `checker` is the event's `$checkerId` (service id),
  else its class; `CheckerRunStatus::of()`: `failed` when `!$success`, `degraded` when `$warned` (any warning — a failed
  non-critical checker adds one), else `passed`; events without `$type` (built by hand) are skipped; each recording has its own try, and a
  failure is logged to channel `metrics_bundle` with the metric it hit.
- `DependencyInjection\CompilerPass\RegisterHealthcheckMetricsPass` appends the enum to
  `msstc4symfony_metrics.metric_enums`; without MetricsBundle or the `msstc4symfony_healthcheck` extension it throws.
- `composer-local.json` (git-ignored) points at `../healthcheck-bundle` for work against an unreleased healthcheck.

Details: `.claude/docs/architecture.md`. **Read `.claude/docs/known-issues.md` before chasing a
"weird" failure.**

## Pointers

- `.claude/docs/architecture.md` — layers, wiring, service ids.
- `.claude/docs/conventions.md` — layout, tests, commits.
- `.claude/docs/known-issues.md` — bridge and family-wide Symfony/CI traps.
