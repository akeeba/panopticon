# New-installation browser test — design

## Why

A fresh installation is the first thing a new user sees of Panopticon. Nothing tests it today. The unit and
integration suites run against a developer checkout, and the `tests/WebServer/` tier deliberately bypasses the setup
wizard (`PANOPTICON_USING_ENV=1`, `PANOPTICON_FINISHED_SETUP=1`).

Translations have broken this first impression before: missing keys or values made pages show AWF's
`BAD TRANSLATION. LANGUAGE KEY “…” HAS THE WRONG NUMBER OR KIND OF VALUE ARGUMENTS.` text. The operator has not
personally seen which installer step broke. `tests/Unit/Language/LanguageFilesTest.php` checks the language files
statically (missing keys, placeholders not matching en-GB). It cannot see a key that is only resolved at runtime, a
plural variant the code asks for, or a string whose placeholders match en-GB but not what the code passes. Only
rendering the real pages catches those.

## What it does

It installs Panopticon the way a new user does and walks the entire setup wizard in a real browser, once per
interface language. It stops at the first successful login.

1. Take the installation ZIP that a release builds (`phing git-app` recompiles the `.ini` language files from their
   `.po` sources and produces `release/panopticon-<version>.zip`).
2. Unzip it into a bare Apache + PHP-FPM + database lab. The container gets **no** `composer install`, no repository
   checkout, no `config.php` and no `PANOPTICON_*` environment configuration; the web root holds only the ZIP's
   contents, exactly as unzipped. *(My call:)* `htaccess.txt` is **not** renamed to `.htaccess`, because the install
   docs don't tell users to do it.
3. Drive the setup wizard with Playwright in its real order (`src/Controller/Setup.php`): language selection (the
   default page) → precheck → database → admin account (`setup`) → save configuration (`saveconfig`; it only shows a
   page when `config.php` can't be written) → CRON → `finish` or `skipcron` → dashboard. Creating the admin account
   also logs it in, so the wizard ends on the dashboard without passing through the login page.
4. Log out, then log in through the login form as the admin account created in step 3, with the interface in the
   run's language, and land on the logged-in dashboard. Done. This covers the login page's strings and proves the
   credentials the user typed really work.

The **session** page (`task=session`, with its FTP form) only appears when neither PHP's session save path nor `tmp/`
is writable, so no normal install reaches it. *(My call:)* every language loads it directly and checks its strings
(render check only, no form submission). If the controller won't render it outside its real trigger, the builder
reports that rather than forcing the condition.

Everything past the first successful login is out of scope: testing the application itself is a different job.

### What counts as a failure, on every page, in every language

- Any `BAD TRANSLATION` text.
- A raw language key shown instead of its text (anything matching `PANOPTICON_[A-Z0-9_]+` in the visible text,
  page title or `alt`/`title`/`placeholder` attributes).
- PHP errors, warnings, notices or deprecations, in the page or in the PHP / Apache error log of the container
  (this is Panopticon's own code, so unlike the Joomla smoke tests warnings and deprecations **do** count).
  `assets/php.ini` sets `error_reporting = 0`, and a fresh install's `error_reporting` option (`default`) leaves PHP's
  setting alone, so nothing would ever surface. *(My call:)* the lab adds a test-only ini file loaded after
  `assets/php.ini` that sets `error_reporting = E_ALL`, `log_errors = On` with a known `error_log` file, and keeps
  `display_errors = Off`, so the `cronHeartbeat` JSON and redirects stay clean. The log is read and cleared per
  install, and any line in it fails that install.
- JavaScript exceptions, `console.error` output, and failed (HTTP ≥ 400) requests for local assets, which would mean
  something is missing from the ZIP.
- Any wizard step that does not lead to the next one, and an install that does not end on the dashboard after login.
- Text that is still English in a non-English run is **not** a failure: the static unit test already enforces
  translation completeness, and many strings (product names, technical terms) are legitimately identical.

## The matrix

PHP bounds come from `composer.json` → `require.php` (currently `>=8.3 <8.7`). "Oldest" is the lowest version that
constraint allows; "newest" is the newest **stable** PHP release it allows. Today that is 8.5, since 8.6 is not
released yet. Database bounds: the documented minimums (MySQL 5.7, MariaDB 10.3) and the newest stable release of
each. The bounds are read or declared in one place and the README explains where each one comes from.

The operator's reasoning, which goes into the README: breaks happen at the edges of the supported range. A new PHP
version's backwards-compatibility breaks will break the newest version if not addressed. Addressing them without
regard for older versions breaks the oldest. The same applies to the database server. Testing both edges catches both.

| Run | PHP | Database | Languages | Path through the wizard |
|-----|-----|----------|-----------|-------------------------|
| Oldest edge | oldest (8.3) | MySQL 5.7 | all 12 | normal, `skipcron` |
| Newest edge | newest (8.5) | newest MySQL | all 12 | normal, `skipcron` — except en-GB, below |
| CRON benchmark | newest | newest MySQL | en-GB only | normal, real CRON benchmark |
| Manual `config.php` | newest | newest MySQL | en-GB only | read-only web root → `saveconfig` page → manual upload → finish |
| MariaDB oldest | oldest | MariaDB 10.3 | en-GB only | normal, `skipcron` |
| MariaDB newest | newest | newest MariaDB | en-GB only | normal, `skipcron` |

The CRON benchmark and the manual `config.php` runs *are* the en-GB installs of the newest edge, so en-GB on the
newest edge is not installed a third time.

PHP and MySQL edges are deliberately paired, oldest with oldest and newest with newest, to keep the run time down. That
makes a failure harder to pin on PHP or MySQL, so command-line options pick any specific PHP and database version
(see below) for bisecting.

The language list is discovered, not hard-coded: every language the installer's language selector offers (see
`helper->setup->languageOptions()` / `getLanguageOptions()`), which today is the 12 `.ini` files in `languages/`.
Adding a language adds it to the test automatically.

### The CRON step

- `skipcron` everywhere except the one benchmark run. Before skipping, every language still loads the CRON page and
  checks it, so its strings are tested in all languages; only the benchmark itself is skipped.
- The benchmark run does what the page tells a real user: a CRON job runs `php cli/panopticon.php task:run` inside the
  PHP container (a loop in the container standing in for crontab) while the page polls `cronHeartbeat` and moves to
  `finish` by itself. The benchmark (`src/Task/MaxExec.php`) burns CPU for up to about 185 seconds, which is accepted:
  it runs once. The benchmark checks if and when an external timeout kills a time-burning CRON job. It does not depend
  on the language or PHP version, so one run is enough.

### The manual `config.php` path

This is the shared-host case the `saveconfig` page exists for. The web root is made read-only to the PHP-FPM user,
while `cache/`, `log/` and `tmp/` stay writable as on a typical shared host, so saving the configuration fails and
the wizard shows the `saveconfig` page. The test takes the configuration text from that page and writes it as
`config.php` into the web root through `docker exec`, as a user uploading it by FTP would. The uploaded `config.php`
stays **read-only** to PHP. The test then follows the page's instructions to continue, takes `skipcron` at the CRON
step, and finishes with the log-out / log-in like every other run.

**Known product bug this run exposes (operator's decision: treat it as a bug and fix it).** `skipcron()` and
`finish()` in `src/Controller/Setup.php` both call `appConfig->saveConfiguration()` without a try/catch.
`Application\Configuration::saveConfiguration()` throws `Can not save …` when `config.php` isn't writable. So today
this path crashes at the CRON step and `finished_setup` is never saved. The plan fixes it **before** this run is
expected to pass:
- a test that fails on today's code (red), then the fix (green), plus a `CHANGELOG` line, in one commit;
- the end state: a user with a read-only `config.php` can finish setup and use Panopticon. *How* (for example, show
  the user the updated configuration to upload again, as `saveconfig` does, or persist `finished_setup` and the CRON
  values some other way) is a product decision. The plan presents the realistic options to the operator and does not
  pick one alone.

## How it runs

### Infrastructure

- **Playwright runs on the host** through the shared toolchain in the sibling `../buildfiles` repository
  (`node ../buildfiles/playwright/run.mjs test`), with its cached Chromium. Specs import from
  `../buildfiles/playwright/lib/index.mjs`, never `@playwright/test` directly, and the config uses
  `defineAkeebaConfig()`. This is required, not a convenience: the operator standardises all projects on `buildfiles`
  so changes there reach every dependent project. `j4-akeeba/akeebabackup/tests/browser/` (relative to the common
  projects root) is the reference implementation; follow its layout and its `run.sh` → `run_browser_tests()` pattern.
  Use `collectPageProblems()` from the shared lib. Panopticon-specific checks (translation errors, raw keys, PHP
  warnings) live in this project's spec helpers. A helper that is generic (not Panopticon-specific) may be proposed for
  `buildfiles`, but ask the operator before changing `buildfiles`.
- **Docker** provides the lab: an Apache 2.4 + PHP-FPM container and a database container. Model the PHP image on
  `tests/WebServer/docker/Dockerfile.fpm`: `php:<ver>-fpm` base, Apache with `proxy_fcgi`, and the app's
  `assets/php.ini`. It must be a **bare** host though, with no `COPY .` of the repository and no app-provisioning
  entrypoint. Install exactly the PHP extensions a typical host has and Panopticon declares: the `ext-*` entries of
  `composer.json` `require`, plus `pdo_mysql`/`mysqli` and OPcache. Enable `mod_rewrite` and allow `.htaccess`, as on
  a normal host.
- Databases run on tmpfs with no persistent volumes. MySQL 5.7 has no arm64 image; on Apple Silicon it runs as
  `linux/amd64` under emulation, which is acceptable. Pick ports and container/project names that don't collide with
  `tests/WebServer/` (port 4290, project `panopticon_webtest`).
- **Isolation:** every language install starts from a fresh unzip and an empty database. The stack can stay up within
  a PHP/database combination while the web root and database are reset between languages; that's the builder's call,
  as long as no state carries over between installs.

### Entry points

- `tests/Installation/run.sh` (exact directory name is the builder's call; keep it alongside `tests/WebServer/`) is
  the only orchestrator. On entry it **always** tears down any leftover stack, and at the end it tears the stack down
  unless `--keep` is given. A crash partway must never leave a stack in an unknown state for the next run.
- `composer test:install` runs it (a `scripts` entry plus a `scripts-descriptions` line). It is **not** part of
  `composer test`/`phing test`: those are the everyday suites, while this is the slower release gate.
- Options:

  | Option | Meaning |
  |--------|---------|
  | *(none)* | the full matrix above |
  | `--zip=<path>` | test this ZIP; default: the newest `release/panopticon-*.zip`, building one with `phing git-app` if there is none |
  | `--php=X.Y` | run only with this PHP version |
  | `--db=mysql:X.Y` / `--db=mariadb:X.Y` | run only with this database server and version |
  | `--lang=de-DE,el-GR` | run only these languages |
  | `--cron` / `--saveconfig` | run only the CRON benchmark / manual `config.php` run |
  | `--keep` | leave the stack up afterwards for debugging |
  | `--down` | tear down any stack and exit |
  | `--headed` | pass through to Playwright, to watch the browser |

  With `--php`/`--db`/`--lang`, run exactly that combination instead of the matrix, so a failure can be bisected to
  its root cause (e.g. `--php=8.3 --db=mysql:8.4` versus `--php=8.5 --db=mysql:5.7`).
- Before touching Docker, `run.sh` checks its prerequisites (Docker with Compose v2, `node` on `PATH`,
  `../buildfiles/playwright/run.mjs` present, a ZIP) and fails with a clear instruction for each one that is missing.
- **Reporting:** one Playwright test per (PHP, database, language, path) combination, named so that the list output
  reads like `PHP 8.3 · MySQL 5.7 · el-GR`. Each failure keeps Playwright's trace and screenshot, plus the container's
  PHP and Apache logs for that install. At the end, a summary lists which combinations failed and at which wizard step.

### Release checklist

The `panopticon-release` skill (`.claude/skills/panopticon-release/SKILL.md`) changes from one command to three:

1. `phing git-app -Dversion=X.Y.Z` rebuilds the `.pot`, recompiles every `.ini` from its `.po`, and builds the ZIP.
   Plain `phing git` skips the language compilation and would ship whatever `.ini` files are committed, so a broken
   `.po` could pass the test and still ship. Commit any `.ini` changes this produces before step 3.
2. `composer test:install` tests that ZIP. Stop the release if it fails.
3. `phing all release update -Dversion=X.Y.Z`. `all` rebuilds the ZIP and is kept because it may grow other
   release-only work, such as documentation.

Keep the skill's existing explanation of why `release`/`update` are separate top-level targets.

### Documentation

`tests/Installation/README.md`, modelled on `tests/WebServer/README.md`: requirements (Docker, Node, the sibling
`buildfiles` checkout with `npm install` and `node playwright/run.mjs install` done once), quick start, the options
table, the matrix with its edge-of-range reasoning and where each bound comes from, how to bisect a failure, where
failure artifacts land, and its place in the release checklist. Also add a one-line pointer to it in `CLAUDE.md`'s
Testing section and in `tests/TESTING.md`.

## Non-goals

- Anything after the first successful login: dashboard features, adding sites, settings.
- CI. It runs locally only, by hand or as a release-checklist step.
- Upgrades from an older version, the Docker image's environment-variable install, and the CLI `config:create`
  install: other entry points, other tests.
- NginX, IIS or `mod_php`: Apache + PHP-FPM is the lab.
- Checking translation *quality* or completeness, which the static unit test already covers.

## Anti-goals (would make this a failure even if it works)

- Testing a developer checkout instead of the release ZIP, or a container that quietly has extra files, Composer
  dev dependencies or pre-seeded configuration. The point is what a new user actually receives.
- A green run that skipped languages: the language list must come from the installer, and a run that tested fewer
  languages than the selector offers must say so loudly.
- Flakiness. Waits are on page state, never fixed sleeps, except where the CRON benchmark inherently takes time.
- Its own Playwright setup or browser download, outside `buildfiles`.

## Left to the builder

The test directory name; the compose layout; how the web root and database are reset between languages; how the
language list is read (the selector's `<option>` values on the first page is the obvious source); the exact
newest MySQL/MariaDB tags; the mechanics of making the web root read-only (ownership or permissions); the spec/file
breakdown under the test directory; and the language the login page is switched to, if logging out resets it.
