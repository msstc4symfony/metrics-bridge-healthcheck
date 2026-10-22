# msstc4symfony/metrics-bridge-healthcheck

![Build Status](https://github.com/msstc4symfony/metrics-bridge-healthcheck/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Exports the results of [healthcheck-bundle](https://github.com/msstc4symfony/healthcheck-bundle) probes as Prometheus
metrics of [metrics-bundle](https://github.com/msstc4symfony/metrics-bundle): how often each checker and each probe
passed, failed or was degraded, and how long each checker took.

## Compatibility

| Bundle | PHP  | Symfony  | healthcheck-bundle | metrics-bundle |
|--------|------|----------|--------------------|----------------|
| 1.0    | 8.2+ | 7.4, 8.x | ^2.2               | ^2.0           |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4.

## Installation

The packages are not on Packagist yet, so register their GitHub repositories first:

```sh
composer config repositories.msstc4symfony-healthcheck vcs https://github.com/msstc4symfony/healthcheck-bundle
composer config repositories.msstc4symfony-metrics vcs https://github.com/msstc4symfony/metrics-bundle
composer config repositories.msstc4symfony-metrics-bridge-healthcheck vcs https://github.com/msstc4symfony/metrics-bridge-healthcheck
composer require msstc4symfony/metrics-bridge-healthcheck
```

Register the bridge in `config/bundles.php` alongside the two bundles it connects (order does not matter):

```php
Msstc4Symfony\MetricsBundle\MetricsBundle::class => ['all' => true],
Msstc4Symfony\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
Msstc4Symfony\MetricsBridgeHealthcheck\MetricsBridgeHealthcheckBundle::class => ['all' => true],
```

Nothing to configure.

## The metrics

| Name                                                     | Type      | Labels                                                   |
|----------------------------------------------------------|-----------|----------------------------------------------------------|
| `symfony_healthcheck_checker_run`                        | counter   | `application`, `component`, `checker`, `probe`, `status` |
| `symfony_healthcheck_checker_duration_histogram_seconds` | histogram | `application`, `component`, `checker`, `probe`           |
| `symfony_healthcheck_probe_run`                          | counter   | `application`, `component`, `probe`, `status`            |

- `symfony_` is metrics-bundle's namespace; `application` / `component` come from its configuration.
- `checker` is the checker's service id, such as `healthcheck.checker.doctrine.dbal.default_connection` for a detected
  one: two checkers of one class (two Redis clients) are two series.
- `probe` is `readiness` or `liveliness` (the bundle's spelling, not Kubernetes' `liveness`).
- `status` of a checker run is `passed`, `failed`, or `degraded` — it passed but added a warning, as a non-critical
  checker that failed does (its probe still passes); of a probe run, `passed` or `failed`. The values are the cases of
  `CheckerRunStatus` and `ProbeRunStatus`.
- Duration buckets (seconds): `0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10`.

The runs are counters rather than up/down gauges: replicas usually share the metrics storage (Redis), where a gauge
would show whichever replica wrote last and hide a failure on another one. Counters add up across replicas, and a
stopped probe shows as a counter that no longer grows. They grow only when a probe runs — the Kubernetes probes, the
`healthcheck:*` commands or any other caller of the endpoints; a run the healthcheck result cache answers counts
nothing. A failure to record a metric is logged as an error to metrics-bundle's `metrics_bundle` channel (not counted
by `symfony_error`) and never fails the probe. Without MetricsBundle or HealthCheckBundle the container fails to
compile with a message naming the missing bundle.

## PromQL

Checkers that failed in the last 5 minutes:

```promql
sum by (checker, probe) (increase(symfony_healthcheck_checker_run{status="failed"}[5m])) > 0
```

Share of failed readiness runs:

```promql
sum(rate(symfony_healthcheck_probe_run{probe="readiness", status="failed"}[5m]))
  / sum(rate(symfony_healthcheck_probe_run{probe="readiness"}[5m]))
```

No readiness run anywhere for 5 minutes (`or vector(0)` also fires when the series does not exist yet):

```promql
(sum(increase(symfony_healthcheck_probe_run{probe="readiness"}[5m])) or vector(0)) == 0
```

p95 duration per checker, to tune the healthcheck timeouts:

```promql
histogram_quantile(0.95, sum by (le, checker) (rate(symfony_healthcheck_checker_duration_histogram_seconds_bucket[15m])))
```

## Public API

The bundle class `Msstc4Symfony\MetricsBridgeHealthcheck\MetricsBridgeHealthcheckBundle` and the metric definition
`Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric` with the `status` values
`CheckerRunStatus` and `ProbeRunStatus` in the same namespace (names, labels, values and buckets are what dashboards
rely on). The listener and the compiler pass are `@internal`.

## Local development

`composer-ci.json` installs healthcheck-bundle and metrics-bundle from their GitHub repositories; metrics-bundle needs
ext-redis.

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration
```

## License

MIT — see [LICENSE](LICENSE).
