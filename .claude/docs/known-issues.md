# Известные ловушки

- **`MetricRecorderInterface` глушит только сбой хранилища** (metrics 2.0): неподходящий тип — `InvalidArgumentException`,
  а чужая реализация порта может бросить что угодно. Каждая запись (increment/observe) — в своём try: healthcheck
  `SafeEventDispatcher` и так глушит исключения слушателя, но тогда молча терялась бы вторая запись и не было бы лога.
- **Лог сбоя — в канал `metrics_bundle`**, а не `app` (2.0): канал не декорируется счётчиком ошибок metrics, иначе
  сбой записи метрики считался бы как ошибка приложения.
- **`beStrictAboutCoverageMetadata`**: unit-тест, исполняющий `src/Resources/config/services.php`, становится risky
  под coverage — загрузку сервисов проверяет только kernel-тест (`#[CoversNothing]`).
- **`kernel.build_dir` в тестовом контейнере.** `PhpFileLoader` Symfony 7.4 читает этот параметр,
  8.x — нет; без него интеграционные тесты падают только в ячейке 7.4.
- **Теги для своих интерфейсов** регистрировать в `loadExtension()` через
  `registerForAutoconfiguration()`, а не `#[AutoconfigureTag]` на интерфейсе: атрибут на интерфейсе
  из vendor-пакета приложение не подхватывает.
- **`gh run watch` может вернуть 0 на упавшем прогоне.** Итог проверять через
  `gh run view <id> --json conclusion,jobs`.
- **`actions/checkout` по sha делает shallow-клон**; push из такого клона отклоняется как
  "fetch first" — нужен `fetch-depth: 0`.
- **Пороги Infection** (`infection-min-msi`, `infection-min-covered-msi` в `checks.yml`) = замер минус запас,
  с датой замера (UTC) в комментарии; пересчитывать при заметном изменении тестов.
- **`exec()` портит вывод с путями**: режет по строкам и срезает пробелы в конце строки, поэтому
  `git ls-files -z` через него теряет символы в именах. Читать через `proc_open` целиком. В тестах не
  читать stdout и stderr из двух pipe по очереди (deadlock при заполненном буфере) — stderr писать в файл.
- **Код должен разбираться на PHP 8.2** (с 2026-10-14 UTC, bundle-standard v2.0.0, профиль `php82`): без
  типизированных констант (`const string X`), без анонимных `new readonly class {}` (оба — 8.3;
  именованный `readonly class` — 8.2, можно) и без `new X()->m()` / `new X()(...)` (8.4) — только
  `(new X())->m()`. PHPStan (`phpVersion.min: 80200`) ловит типизированные
  константы; остальное ловят только `php -l` в job «minimal» (PHP 8.2) и ячейки PHPUnit на 8.2. Шаблоны Rector
  (`withPhpVersion(PHP_82)`) и CS-Fixer (`new_expression_parentheses`) синтаксис обратно не поднимают.
  `#[Override]` (8.3) допустим: на 8.2 атрибут не инстанцируется. `array_any/all/find` (8.4) — только с
  `symfony/polyfill-php84` в `require`. Symfony 8 ставится только на PHP 8.4+.
  `composer-ci.lock` собран на PHP 8.4 (Symfony 8): на 8.2/8.3 локально — `COMPOSER=composer-ci.json composer update`
  (или `--prefer-lowest`, как ячейка CI), не `install`.
- **Эквивалентные мутанты (2):** `parent::build()` в бандле (`AbstractBundle::build()` пуст, вызов оставлен по
  конвенции семейства) и `?->` у логгера в `HealthcheckMetricsListener::reportFailure()` (при `null` ошибку ловит
  внутренний catch). Covered MSI 96 % (2026-10-22 UTC), пороги CI 90.
- **`composer-local.json`** (в `.gitignore`) — `composer-ci.json` + path-репозиторий `../healthcheck-bundle` с версией
  2.2.0: для работы против невыпущенного healthcheck (`COMPOSER=composer-local.json composer update`).
- **`composer-ci.lock` в git**, как у остальных пакетов семейства: job `setup` CI ставит зависимости по нему. Локально
  `roave/security-advisories` конфликтует с PHP 8.4.17 — lock обновлять с `--ignore-platform-req=php`, работать через
  `composer-local.json`.
