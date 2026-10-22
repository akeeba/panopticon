/**
 * Installs Panopticon from the release ZIP and walks the whole setup wizard, once per interface language and wizard
 * path, ending at the first successful login. Driven by tests/Installation/run.sh, which sets:
 *
 *   INSTALLTEST_BASE_URL  Where the lab answers, e.g. http://localhost:4291
 *   INSTALLTEST_COMBO     Label of the PHP / database combination, e.g. "PHP 8.3 · MySQL 5.7"
 *   INSTALLTEST_LANGS     Comma-separated languages, e.g. "en-GB,de-DE"
 *   INSTALLTEST_PATHS     Comma-separated wizard paths: normal, cron, saveconfig
 *
 * lib/lab.mjs also reads INSTALLTEST_COMPOSE_FILE and INSTALLTEST_PROJECT, and `docker compose` needs the lab's own
 * variables (INSTALLTEST_PHP, INSTALLTEST_DB_IMAGE, INSTALLTEST_ZIP) to parse the compose file.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {test} from "../../../../../buildfiles/playwright/lib/index.mjs";
import {watchPage} from "../lib/checks.mjs";
import {resetLab} from "../lib/lab.mjs";
import {
    checkSessionPage, chooseLanguage, createAdmin, fillDatabase, logOutAndBackIn, makeAdmin, passPrecheck, skipCron,
} from "../lib/wizard.mjs";

const list = (value) => (value ?? "").split(",").map((item) => item.trim()).filter(Boolean);

const combo = process.env.INSTALLTEST_COMBO ?? "manual";
const langs = list(process.env.INSTALLTEST_LANGS);
const paths = list(process.env.INSTALLTEST_PATHS || "normal");

/**
 * The steps of each wizard path, in order. Each step gets the page and the install's context.
 */
const PATHS = {
    normal: [
        ["choose language", chooseLanguage],
        ["precheck", passPrecheck],
        /**
         * Before the database step on purpose: once the admin account step has written config.php, the Setup
         * controller sends every task but the CRON ones to the CRON page, so the session page no longer renders.
         */
        ["session page", checkSessionPage],
        ["database", fillDatabase],
        ["admin account", createAdmin],
        ["skip CRON", skipCron],
        ["log out and back in", logOutAndBackIn],
    ],
};

// A typo or an unsupported path must not turn into a green run that tested nothing.
const unknown = paths.filter((path) => !PATHS[path]);

if (unknown.length)
{
    throw new Error(`Unsupported installation path(s) in INSTALLTEST_PATHS: ${unknown.join(", ")}. `
        + `Supported: ${Object.keys(PATHS).join(", ")}.`);
}

if (!langs.length)
{
    throw new Error("INSTALLTEST_LANGS is empty: there is no language to install in.");
}

for (const lang of langs)
{
    for (const path of paths)
    {
        test(`${combo} · ${lang} · ${path}`, async ({page}) =>
        {
            // The page fixture is a fresh browser context per test: no session survives from the previous install.
            const ctx = {lang, watcher: watchPage(page), admin: makeAdmin()};

            await test.step("reset the lab", () => resetLab());

            for (const [name, run] of PATHS[path])
            {
                await test.step(name, () => run(page, ctx));
            }
        });
    }
}
