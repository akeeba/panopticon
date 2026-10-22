# New-installation browser test — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `composer test:install` release gate that installs the release ZIP into a bare Apache + PHP-FPM + MySQL /
MariaDB lab and walks the whole setup wizard in every interface language with Playwright, ending on a fresh login.

**Architecture:** `tests/Installation/run.sh` brings up one Docker lab per (PHP, database) combination of the matrix
and runs the shared `../buildfiles` Playwright toolchain on the host against it. Before each install, the spec resets
the lab to a fresh unzip and an empty database through a reset script inside the PHP container. A product bug that
the read-only `config.php` path exposes is fixed first, in Task 1.

**Tech Stack:** Bash, Docker Compose v2, `php:<ver>-fpm` + Apache 2.4, MySQL 5.7 / newest, MariaDB 10.3 / newest,
Node + `../buildfiles/playwright` (`@playwright/test` 1.64.1, Chromium), PHPUnit (fix only).

**Spec:** `docs/plans/2026-10-22-installation-test-design.md`. Read it first; this plan argues from it.

## Global Constraints

- Test **only** what `phing git-app` puts in `release/panopticon-<version>.zip`, unzipped as is. No repo checkout,
  no Composer, no `config.php`, no `PANOPTICON_*` environment variables, `htaccess.txt` **not** renamed.
- Playwright runs **on the host** via `node ../buildfiles/playwright/run.mjs`. Specs import only from
  `../../../../buildfiles/playwright/lib/index.mjs` (relative to the spec), never `@playwright/test`. Don't change
  `buildfiles` without asking the operator.
- Matrix (the spec's table): PHP oldest 8.3 + MySQL 5.7 + all languages; PHP newest 8.5 + newest MySQL + all
  languages, where en-GB runs only as the `cron` and `saveconfig` paths; PHP 8.3 + MariaDB 10.3 + en-GB;
  PHP 8.5 + newest MariaDB + en-GB.
- The oldest PHP version is read from `composer.json` `require.php`. The newest PHP version and the database
  versions are declared in **one** block at the top of `run.sh`, each with a comment saying where it comes from.
- The language list comes from the installer's language `<select id="language">` (option values other than `""`),
  never a hard-coded list. A run filtered with `--lang` prints a loud warning naming the languages it skipped.
- On every page in every run, fail on: `BAD TRANSLATION`; `\bPANOPTICON_[A-Z0-9_]+\b` in visible text, `<title>`, or
  `alt`/`title`/`placeholder`/`aria-label`/`value` (buttons) attributes; `PHP_ERROR_PATTERN` or
  `/(Warning|Notice|Deprecated):/` in the page; any line in the PHP error log; Apache error-log lines at level
  `error` or above or containing `PHP message`; `collectPageProblems()` findings; a document response ≥ 400.
- No fixed sleeps, except the one documented 3-second wait for `opcache.revalidate_freq=2` after uploading a
  replacement `config.php` (Task 5). The benchmark wait is a wait on page state with a long timeout.
- Ports and names must not collide with `tests/WebServer/` (port 4290, project `panopticon_webtest`). Use compose
  project `panopticon_installtest` and port `4291`.
- `run.sh` scrubs any previous stack on entry and tears down on exit (trap) unless `--keep`.
- PHP: tabs, Allman, `defined('AKEEBA') || die;`, `@package panopticon` docblock. JS: 4-space indent, double quotes,
  semicolons, Allman. Lines ≤ 120 characters. Every commit ends with `Signed-off-by:` for the operator (`git commit -s`).
- Not part of `composer test` / `phing test`. Runs locally only. No CI.

## Review Focus

1. **A language whose selector value doesn't match its `.ini`**, or a language that makes the wizard redirect
   elsewhere. Expected: that language's test fails at the language step and names it. Task 3's spec asserts that the
   welcome heading changed from its en-GB text for every non-en-GB run.
2. **A leftover from the previous install** (session cookie, `config.php`, DB tables) makes the next language skip
   wizard steps and pass green. Expected: every install starts on the welcome page. Task 3 asserts that the first
   navigation renders the welcome page with the language selector, and uses a fresh browser context per test.
3. **The wizard itself fails** (database step error banner, setup step validation error) but the page still renders
   cleanly. Expected: a failure that names the step. Task 3 asserts the URL/`task` reached after each step, not just
   the absence of errors.
4. **Logging out drops the forced language**, so the login page is checked in English and the run passes without
   testing the language's login strings. Task 3 asserts the login page renders in the run's language (the same
   `<html lang>` check).
5. **A legitimate literal `PANOPTICON_…` text** (e.g. an environment variable name in the CRON instructions)
   triggers a false positive. Expected: the en-GB run is green. Task 3 keeps an explicit allowlist in `checks.mjs`;
   each entry carries a comment naming the string and the page it's on. Start empty and add only what the first green
   en-GB run proves necessary.

---

### Task 1: Setup can finish with a read-only `config.php`

The product fix, with red/green proof. One commit holds the fix, its test and the CHANGELOG line.

**Files:**
- Modify: `src/Controller/Setup.php` (`skipcron()` ~234, `finish()` ~262)
- Modify: `src/View/Setup/Html.php` (new property)
- Modify: `ViewTemplates/Setup/saveconfig.php:83` (the "Next" link)
- Create: `tests/Integration/Controller/Setup/ReadOnlyConfigTest.php`
- Modify: `CHANGELOG`

**Interfaces:**
- Produces: `Html::$saveConfigNextUrl` (`public ?string $saveConfigNextUrl = null`). The `saveconfig` template links
  "Next" to it when it's set, and to `index.php?view=setup&task=cron` otherwise (today's behaviour).
- Produces: `private function saveFinalConfiguration(): bool` in `Controller\Setup`. It calls
  `appConfig->saveConfiguration()` plus the existing `opcache_invalidate`, catches `Exception` (as `saveconfig()`
  does; `ReadOnlyRepository` and the `Can not save` error are both `RuntimeException`) and returns `false` on
  failure. Both `skipcron()` and `finish()` use it.
- Behaviour: `installDefaultTasks()` still runs. When `saveFinalConfiguration()` returns `false`, the action sets the
  `saveconfig` layout, sets `$saveConfigNextUrl` to `route('index.php?view=main')`, and calls `display()` with no
  redirect. The page then shows the **final** configuration (`finished_setup`, `max_execution`,
  `cron_stuck_threshold`, `execution_bias` already set in memory) for the user to upload again. No new language
  strings.

- [ ] **Step 1: Write the failing test**

`ReadOnlyConfigTest extends AbstractControllerIntegrationTestCase`. In `setUp`, keep the original `finished_setup`
and set it `false` in memory, set `segment` `panopticon.installing` to `true`, and `loginAs()` a new super user
(`createUser(['parameters' => ['acl.panopticon.super' => 1]])`). In `tearDown`, restore all three. The test
environment loads its configuration from `.env.test`, so `saveConfiguration()` already throws `ReadOnlyRepository`.
That's the read-only condition, with no mocking needed.

- `testSkipcronWithReadOnlyConfigShowsFinalConfiguration`: inside `ob_start()`/`ob_get_clean()`,
  `dispatch(Setup::class, 'skipcron')`. Assert: no exception; `$controller->getView()->getLayout() === 'saveconfig'`;
  the output contains `finished_setup` and `view=main`, and does not contain `task=cron`.
- `testFinishWithReadOnlyConfigShowsFinalConfiguration`: the same with `dispatch(Setup::class, 'finish',
  ['maxexec' => 60])`. Also assert the output contains `max_execution`.

- [ ] **Step 2: Run it to make sure it fails**

Run: `composer test:integration -- --filter ReadOnlyConfigTest`
Expected: both tests fail with an uncaught `ReadOnlyRepository`. Keep this output for the commit message and the
final report. If they fail for any other reason (redirect, ACL), fix the test setup until the failure is the
exception, then continue.

- [ ] **Step 3: Implement it as described in Interfaces.**

- [ ] **Step 4: Run it to make sure it passes, then run the whole suite**

Run: `composer test:integration -- --filter ReadOnlyConfigTest`, then `composer test`. Expected: all green.

- [ ] **Step 5: CHANGELOG + commit**

Check with `git tag -l 2.4.0` whether 2.4.0 has been released. If it has, start a `Akeeba Panopticon 2.4.1` section
at the top; if not, add the line to the 2.4.0 section in sort order (with the other `# [HIGH]` lines):
`# [HIGH] Setup could never finish when config.php was not writable after uploading it by hand`.
Commit (`git commit -s`), quoting the red run in the message.

---

### Task 2: The bare lab

**Files:**
- Create: `tests/Installation/docker/compose.yml`
- Create: `tests/Installation/docker/Dockerfile.php`
- Create: `tests/Installation/docker/apache-vhost.conf`
- Create: `tests/Installation/docker/zz-installtest.ini`
- Create: `tests/Installation/docker/entrypoint.sh`
- Create: `tests/Installation/docker/lab-reset.php`

**Interfaces:**
- Produces: compose project `panopticon_installtest`, with services `php` (Apache + FPM, host port
  `${INSTALLTEST_PORT:-4291}` → 80) and `db`, selected by env vars `INSTALLTEST_PHP` (e.g. `8.3`),
  `INSTALLTEST_DB_IMAGE` (e.g. `mysql:5.7`, `mariadb:10.3`) and `INSTALLTEST_ZIP` (absolute host path, mounted
  read-only at `/lab/panopticon.zip`).
- Produces the database credentials the wizard types in: host `db`, database `panopticon`, user `panopticon`,
  password `panopticon`, prefix `pnptc_`. Root password `installtest`, used only by `lab-reset.php`.
- Produces `docker compose exec -T php php /lab/lab-reset.php [--readonly]`. It drops and recreates database
  `panopticon` (grants re-applied) as root over `mysqli`; empties `/var/www/html`; unzips `/lab/panopticon.zip` into
  it; and truncates `/var/log/php/error.log` and `/var/log/apache2/error.log`. Default: `chown -R www-data` the web
  root. With `--readonly`: root owns the web root (`a+rX`) and only `cache/`, `log/` and `tmp/` belong to
  `www-data`. It prints `RESET OK` and exits with 0, or exits non-zero with the reason.
- Produces the PHP error log at `/var/log/php/error.log` (readable through `docker compose exec`).

- [ ] **Step 1: Write the image and compose file**

- `Dockerfile.php`: `FROM php:${PHP_VERSION}-fpm`. Install `apache2`, `unzip`, `libzip-dev`; run
  `docker-php-ext-install mysqli pdo_mysql zip opcache`. The base image already provides curl, dom, json, mbstring,
  openssl, simplexml and zlib; check each with `php -m` in the built image. Copy `assets/php.ini` and then
  `zz-installtest.ini` into `conf.d/` (the `zz-` prefix loads it last). Run `a2enmod proxy proxy_fcgi rewrite setenvif`
  and set `AllowOverride All` in the vhost. No `COPY .` of the repository: the build context is `tests/Installation/docker`,
  plus `assets/php.ini` passed through a second named context (`additional_contexts`) or copied by `run.sh` into a
  git-ignored `docker/build/`. Builder's call.
- `zz-installtest.ini`: `error_reporting = E_ALL`, `display_errors = Off`, `log_errors = On`,
  `error_log = /var/log/php/error.log`.
- `entrypoint.sh`: create `/var/log/php` (owned by `www-data`), then `php-fpm -D` and `exec apache2ctl -D FOREGROUND`.
  Nothing else; the lab installs nothing by itself.
- `compose.yml`: `db` uses `${INSTALLTEST_DB_IMAGE}` on tmpfs, with `platform: linux/amd64` **only** for
  `mysql:5.7` (no arm64 image). Set it through `INSTALLTEST_DB_PLATFORM`, which `run.sh` fills in. Use the MySQL
  default authentication flag only where the image needs it. Add a `healthcheck` on `db` and have `php` wait for it
  with `depends_on: condition: service_healthy`.

- [ ] **Step 2: Verify the lab by hand**

Run (with any built ZIP):
`INSTALLTEST_PHP=8.5 INSTALLTEST_DB_IMAGE=mysql:<newest> INSTALLTEST_ZIP=$PWD/release/panopticon-<v>.zip docker
compose -f tests/Installation/docker/compose.yml -p panopticon_installtest up -d --build --wait`, then
`docker compose … exec -T php php /lab/lab-reset.php`, then
`curl -s http://localhost:4291/index.php | grep -c 'id="language"'`.
Expected: `RESET OK`, then `1`. Repeat with `INSTALLTEST_PHP=8.3 INSTALLTEST_DB_IMAGE=mysql:5.7` and with
`mariadb:10.3`. Run `--readonly` once and check with `docker compose exec -T php ls -ld /var/www/html /var/www/html/tmp`.
Bring the stack `down -v`.

- [ ] **Step 3: Commit** (`git commit -s`).

---

### Task 3: The install spec, normal path

**Files:**
- Create: `tests/Installation/browser/playwright.config.mjs`
- Create: `tests/Installation/browser/lib/lab.mjs`
- Create: `tests/Installation/browser/lib/checks.mjs`
- Create: `tests/Installation/browser/lib/wizard.mjs`
- Create: `tests/Installation/browser/specs/install.spec.mjs`
- Create: `tests/Installation/browser/.gitignore` (`results/`, `report/`)

**Interfaces:**
- Consumes: Task 2's compose project, the `lab-reset.php` contract and the DB credentials.
- Reads env (set by `run.sh` in Task 4): `INSTALLTEST_BASE_URL`, `INSTALLTEST_COMBO` (label, e.g.
  `PHP 8.3 · MySQL 5.7`), `INSTALLTEST_LANGS` (comma list), `INSTALLTEST_PATHS` (comma list from `normal`, `cron`,
  `saveconfig`), `INSTALLTEST_COMPOSE_FILE`, `INSTALLTEST_PROJECT`.
- `lab.mjs` produces: `resetLab({readonly = false} = {}): void`, `exec(args: string[]): string` (runs
  `docker compose exec -T php …`), `readServerProblems(): string[]` (PHP log lines plus qualifying Apache lines),
  `writeConfig(phpSource: string): void` (writes `/var/www/html/config.php` as root, `0644`), and
  `startCronJob(): void` / `stopCronJob(): void` (used in Task 5).
- `checks.mjs` produces: `async assertPageClean(page, {step: string, watcher}): Promise<void>`, which applies every
  page check in Global Constraints and throws with `step` in the message, and `RAW_KEY_ALLOWLIST: string[]` (starts
  empty, see Review Focus 5).
- `wizard.mjs` produces one function per step, each taking `(page, ctx)` with
  `ctx = {lang, watcher, admin: {name, email, username, password}}` and ending with an `assertPageClean` call and an
  assertion of the page/`task` it reached:
  `chooseLanguage`, `passPrecheck`, `fillDatabase`, `checkSessionPage`, `createAdmin`, `skipCron`,
  `logOutAndBackIn`. Task 5 adds `runCronBenchmark` and `uploadConfigManually`.
- `playwright.config.mjs`: `defineAkeebaConfig({baseURL: process.env.INSTALLTEST_BASE_URL, testDir: "specs",
  reporter: [["list"], ["html", …], ["json", {outputFile: "results/report.json"}]]})`. `run.sh` sets the output
  directories per combination.

- [ ] **Step 1: Write the spec**

`install.spec.mjs` generates one `test()` per `(lang, path)` from the env, titled
`` `${combo} · ${lang} · ${path}` ``. Each test uses its own context: the `page` fixture, which is fresh per test. It
calls `resetLab()` and runs the steps for its path inside `test.step(name, …)`:
- `normal`: choose language → precheck → database → admin → (`checkSessionPage`: open
  `index.php?view=setup&task=session&layout=session` in a second page of the same context, check it, close it) →
  `skipCron` → dashboard → `logOutAndBackIn`.

Step details the implementer can't guess:
- Language: go to `/index.php`, assert the welcome page (`#language` is visible), select the language in `#language`
  (the select auto-submits with `?language=`), then click the primary "Let's go" link. For every language other than
  en-GB, first load `/index.php?view=setup&language=en-GB` and record the welcome `h3` text. After choosing the run's
  language, assert the `h3` text differs from it, which proves the language switch took effect (Review Focus 1). Use
  the same comparison against the en-GB login heading for Review Focus 4.
- Precheck: wait for `#systemsGo` to lose `d-none`, then click its primary link. If `#requirementsCheck` shows unmet
  requirements, fail with that section's text.
- Database: fill `#host`, `#user`, `#pass`, `#name`, `#prefix`, submit `#dbFormSubmit`, then assert the URL reached
  `task=setup`.
- Admin: fill `#name`, `#email`, `#username`, `#password`, `#password2` and submit. Use a password that passes the
  complexity checks and isn't in a breach list: build a random one per run, never a constant. Assert the URL reached
  `task=cron` (or `saveconfig` on that path).
- `skipCron`: run `assertPageClean` on the CRON page as it is, then click the `task=skipcron` link, then assert
  `view=main`.
- `logOutAndBackIn`: click the user-menu log-out link (`a[href*="task=logout"]`; open its dropdown first) and assert
  `view=login`. If the login page isn't in the run's language (Review Focus 4), switch it with the login page's own
  `#language` selector, which is what a user would do. Fill `#username`/`#password`, click `#btnLoginSubmit`,
  assert `view=main` and run `assertPageClean`.
- After every step, check `readServerProblems()` is empty, naming the step.

- [ ] **Step 2: Run it against a kept-up lab, en-GB only**

Bring the lab up as in Task 2 Step 2 (8.5 + newest MySQL), then from `tests/Installation/browser`:
`INSTALLTEST_BASE_URL=http://localhost:4291 INSTALLTEST_LANGS=en-GB INSTALLTEST_PATHS=normal
INSTALLTEST_COMBO=manual … node ../../../../buildfiles/playwright/run.mjs test`.
Expected: 1 passed. Then run with `INSTALLTEST_LANGS=de-DE,el-GR`. Expected: 2 passed.

- [ ] **Step 3: Prove the checks can fail**

Temporarily (do not commit) edit, inside the running container, one en-GB string used on the precheck page to
`%s %s`, and separately rename one key. Re-run en-GB. Expected: the first fails with `BAD TRANSLATION` naming the
precheck step, the second with the raw key. Also put `trigger_error('x', E_USER_DEPRECATED);` into `index.php` in the
container. Expected: a failure from the PHP log. Reset the lab.

- [ ] **Step 4: Commit** (`git commit -s`).

---

### Task 4: `run.sh`, the matrix and `composer test:install`

**Files:**
- Create: `tests/Installation/run.sh` (executable)
- Create: `tests/Installation/summary.mjs`
- Modify: `composer.json` (`scripts`: `"test:install": "tests/Installation/run.sh"`, with
  `"process-timeout": 0` handled, e.g. `Composer\\Config::disableProcessTimeout` first in the script array)
- Modify: `.gitignore` (`/tests/Installation/results/`, plus the Task 2 build directory if used)

**Interfaces:**
- Consumes: Tasks 2 and 3's env contract.
- Options exactly as in the spec's table: `--zip=`, `--php=`, `--db=mysql:X.Y|mariadb:X.Y`, `--lang=`, `--cron`,
  `--saveconfig`, `--keep`, `--down`, `--headed`, plus `--help`. Validate each value. Unknown option → exit 2.
- Produces: `tests/Installation/results/<combo-slug>/` (Playwright output, `report.json`, and the PHP/Apache logs
  copied out after each combination) and a final summary printed by `node summary.mjs results/*/report.json`:
  one line per failed test, giving the combination, language, path and the `test.step` it failed in. The exit code is
  non-zero if anything failed.

- [ ] **Step 1: Write `run.sh`**

In order:
1. Prerequisites: `docker compose version`, `node`, `../buildfiles/playwright/run.mjs`, each failing with the fix. Run
   `node …/run.mjs install` once.
2. Resolve the ZIP: `--zip`, else the newest `release/panopticon-*.zip`, else run `phing git-app` and use what it
   built. Print the ZIP path and version.
3. Matrix block: `PHP_OLDEST` comes from `composer.json` through `php -r` (`>=8.3` → `8.3`). `PHP_NEWEST=8.5` carries
   the comment "newest stable PHP allowed by require.php; 8.6 is not released yet". Also declare `MYSQL_OLDEST=5.7`,
   `MARIADB_OLDEST=10.3` ("documented minimums, see the wiki's Install-Panopticon page"), and `MYSQL_NEWEST` /
   `MARIADB_NEWEST`. For these two, check Docker Hub at implementation time for the newest GA tag, pin `major.minor`,
   and record the date in the comment. Then build the combination list from the spec's table, each with its
   languages (`all` or `en-GB`) and paths.
4. `--php`/`--db`/`--lang`/`--cron`/`--saveconfig` replace the matrix with exactly the one combination asked for.
   The defaults for anything not given are the newest PHP, the newest MySQL, all languages and the `normal` path.
5. `trap` cleanup, then scrub with `docker compose … down -v --remove-orphans`. `--down` only does this and exits.
6. For each combination: `up -d --build --wait`, then run `lab-reset.php`. Discover the languages with `curl` + `sed`
   over `<select id="language">` `option value="…"` (skip `""`). Expand `all`. With `--lang`, warn loudly about the
   discovered languages that were left out. For the newest MySQL edge, put en-GB on the paths `cron,saveconfig`
   instead of `normal`. Run Playwright with the Task 3 env and per-combination output directories, copy the container
   logs into the results directory, then `down -v` (unless `--keep` and this is the last combination).
7. Print the summary and exit with the overall status.

- [ ] **Step 2: Verify**

Run: `composer test:install -- --php=8.5 --db=mysql:<newest> --lang=en-GB,fr-FR`. Expected: 2 passed, the loud
`--lang` warning listing the other 10 languages, the stack gone afterwards (`docker ps` shows nothing for
`panopticon_installtest`). Run `tests/Installation/run.sh --keep --lang=en-GB`, then `--down`. Expected: the stack is
up after the first command and gone after the second. Run with `--db=postgres:1`. Expected: exit 2.

- [ ] **Step 3: Commit** (`git commit -s`).

---

### Task 5: The CRON benchmark and manual `config.php` paths

**Files:**
- Modify: `tests/Installation/browser/lib/wizard.mjs`
- Modify: `tests/Installation/browser/lib/lab.mjs` (`startCronJob`/`stopCronJob`, if not done in Task 3)
- Modify: `tests/Installation/browser/specs/install.spec.mjs`

**Interfaces:**
- Consumes: Task 1's behaviour (the second `saveconfig` page whose Next button goes to `view=main`), Task 3's helpers.
- Produces: `runCronBenchmark(page, ctx)`, `uploadConfigManually(page, ctx)`, and the `cron` / `saveconfig` paths in
  the spec.

- [ ] **Step 1: Write the two paths**

- `cron`: normal steps up to the CRON page, then `startCronJob()`. That starts a detached loop in the `php` container,
  as `www-data` in `/var/www/html`: `while true; do php cli/panopticon.php task:run; sleep 60; done`. It stands in
  for crontab and is stopped in a `finally` with `stopCronJob()`. Then wait (timeout 5 minutes, `test.setTimeout`
  ~8 minutes) for the URL to reach the finish page that the page's own JS moves to. Then `assertPageClean`, click the
  finish page's dashboard link, and `logOutAndBackIn`. If `#error` loses `d-none`, fail with `#errorMessage`'s text.
- `saveconfig`: `resetLab({readonly: true})`, then the normal steps up to the admin step. Assert the URL stays on
  `task=saveconfig` with `#configSource` visible. Take `#configSource`'s `innerText`, then `writeConfig()` and
  click Next. On the CRON page, `skipCron` now leads to the **second** `saveconfig` page (Task 1). Assert it, and
  assert `#configSource` contains `finished_setup`. Write that config, wait 3 seconds (comment: opcache revalidates
  `config.php` every 2 s, `assets/php.ini`), click Next, assert `view=main`, then `logOutAndBackIn`.

- [ ] **Step 2: Verify**

Run: `composer test:install -- --cron` and `composer test:install -- --saveconfig`. Expected: 1 passed each; the cron
run takes about 3–4 minutes. Then build a ZIP from Task 1's parent commit (`git worktree add` there and run
`phing git-app`), and run `--saveconfig --zip=<that ZIP>`. Expected: it fails at the CRON step with "Can not save".
That's the end-to-end red for Task 1; quote it in the final report and remove the worktree.

- [ ] **Step 3: Commit** (`git commit -s`).

---

### Task 6: Documentation, release checklist, full run

**Files:**
- Create: `tests/Installation/README.md`
- Modify: `.claude/skills/panopticon-release/SKILL.md`
- Modify: `CLAUDE.md` (Testing section: one line pointing to `tests/Installation/README.md` and `composer test:install`)
- Modify: `tests/TESTING.md` (the same pointer)

- [ ] **Step 1: Write the README**

Model it on `tests/WebServer/README.md`. Sections:
- What it proves and why it exists (first impression; the BAD TRANSLATION history).
- Requirements: Docker with Compose v2, Node, and the sibling `../buildfiles` checkout with `npm install` done.
- Quick start.
- The options table.
- The matrix table, with the operator's edge-of-range reasoning (in the spec) and where each bound comes from.
- Why PHP and MySQL edges are paired, and how to bisect a failure with `--php`/`--db`/`--lang`, with an example.
- Where the results and logs land.
- The two special en-GB runs, and why only en-GB on the newest edge.
- Its place in the release checklist.

- [ ] **Step 2: Update the release skill**

Replace the single-command instruction with the spec's three steps (`phing git-app -Dversion=X.Y.Z` → commit any
`.ini` changes → `composer test:install`, stopping on failure → `phing all release update -Dversion=X.Y.Z`). Keep
the existing explanation of why `release`/`update` are separate top-level targets.

- [ ] **Step 3: Full run**

Run: `phing git-app -Dversion=git` (or use the latest ZIP), then `composer test:install`.
Expected: every combination green (12 + 11 + 2 + 1 + 1 = 27 tests) and an empty failure summary. If a language
fails because of a real translation or product bug, don't weaken the check. Record it and report it to the operator
with the failing step and screenshot path. Ask before fixing it here.

- [ ] **Step 4: Commit** (`git commit -s`).
