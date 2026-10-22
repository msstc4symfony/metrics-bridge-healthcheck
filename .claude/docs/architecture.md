# Архитектура

- `MetricsBridgeHealthcheckBundle` — `AbstractBundle` без дерева конфигурации: `build()` добавляет
  `RegisterHealthcheckMetricsPass`, `loadExtension()` импортирует `src/Resources/config/services.php`.
- `Infrastructure/Metrics/`:
  - `HealthcheckMetric` — enum трёх метрик (`Application\Metric\MetricDefinitionInterface` metrics 2.0):
    `healthcheck_checker_run` (counter; `checker`, `probe`, `status` passed/failed/degraded),
    `healthcheck_checker_duration_histogram_seconds` (histogram; `checker`, `probe`), `healthcheck_probe_run`
    (counter; `probe`, `status` passed/failed). `probe`/`status` — ENUM-лейблы; значения `status` из enum'ов
    `CheckerRunStatus`/`ProbeRunStatus`. Публичный API моста вместе с бандлом.
  - `HealthcheckMetricsListener` — `#[AsEventListener]` на `HealthCheckerCompletedEvent`/`HealthCheckRunCompletedEvent`
    healthcheck 2.2; `MetricRecorderInterface` + `?LoggerInterface` (`#[WithMonologChannel('metrics_bundle')]`).
    Метки `application`/`component` добавляет адаптер metrics, мост передаёт `[checker, probe]` / `[probe]`.
- `DependencyInjection/CompilerPass/RegisterHealthcheckMetricsPass` — дописывает enum в
  `msstc4symfony_metrics.metric_enums` (идемпотентно); без параметра metrics или расширения `msstc4symfony_healthcheck`
  — `LogicException` с понятным текстом (расширение, а не параметр healthcheck: его id — публичная конфигурация).
- Сервисы приватные, id = FQCN.
- deptrac (шаблон v3): Infrastructure ни от чего; DependencyInjection → Infrastructure (pass ссылается на enum);
  BundleRoot → DependencyInjection.
- `@internal`: слушатель, pass. Unit-тесты — на `tests/Fixtures/RecordingMetricRecorder` (порт), kernel-тест —
  настоящий registry (`inmemory://`) и настоящие HealthCheckBundle + MetricsBundle.

## Решения

- `checker` = `HealthCheckerCompletedEvent::$checkerId` (id сервиса), а не метка и не класс: метки не уникальны
  (у всех клиентов Redis «Redis connection»), класс у двух клиентов один. Без id (Action собран руками списком) — класс.
- Counter со `status`, а не gauge up/down (ревью 2026-10-22): реплики обычно пишут в один Redis, а меток инстанса
  нет — gauge показывал бы последнего писавшего и прятал сбой на другой реплике. Counter суммируется; «пробы
  не идут» = `increase(...) == 0`, отдельный timestamp не нужен.
- `degraded` = `$success && $warned`: некритичный чекер при сбое пишет warning, его `$success` остаётся true, проба
  остаётся `passed` (`Response::$success`). Warning может добавить и обычный чекер (`CheckResult::addWarning()`
  публичный) — он тоже `degraded`. Значения статусов — enum'ы `CheckerRunStatus`/`ProbeRunStatus` (публичный API).
- Алерт «пробы не идут»: `(sum(increase(...probe_run[5m])) or vector(0)) == 0` — без `or vector(0)` пустой результат
  (серии ещё нет, Redis очищен) не сработал бы.
- Событие без `$type` (собрано руками) не пишется: серия без пробы была бы с неверной меткой.
- Ответ из кэша результата событий не диспатчит и метрики не трогает (`testARunTheResultCacheAnswersCountsNothing`).
- `increment` и `observe` в отдельных try: сбой одной записи не теряет другую, а лог называет метрику, на которой упал.
