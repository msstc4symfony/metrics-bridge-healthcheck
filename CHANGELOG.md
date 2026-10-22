# Changelog

## 1.0.0 - 2026-10-22

- First release: `symfony_healthcheck_checker_run{checker, probe, status}` and `symfony_healthcheck_probe_run{probe,
  status}` counters and `symfony_healthcheck_checker_duration_histogram_seconds{checker, probe}` from healthcheck-bundle
  2.2 probe runs. `checker` is the checker's service id; a checker that passed with a warning (a failed non-critical one) is
  `degraded`.
