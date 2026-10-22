/**
 * The setup wizard, one function per step, driven the way a new user drives it: through the visible links, selects
 * and forms. Every step checks the page it acts on with assertPageClean() before leaving it, then asserts which page
 * its action reached. A step whose form "fails nicely" (an error banner, back to the same step) therefore fails the
 * test under that step's name, instead of looking like a clean page.
 *
 * Every function takes `(page, ctx)` with `ctx = {lang, watcher, admin: {name, email, username, password}}`.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {randomBytes} from "node:crypto";
import {assertPageClean, assertServerClean, watchPage} from "./checks.mjs";

/**
 * Database settings of the lab (tests/Installation/docker/compose.yml).
 */
const DATABASE = {host: "db", user: "panopticon", pass: "panopticon", name: "panopticon", prefix: "pnptc_"};

/**
 * A new admin account. The password is random per call so it can never be in a breach list (the setup checks it
 * against Have I Been Pwned) and is long and mixed enough for the complexity check.
 *
 * @returns {{name: string, email: string, username: string, password: string}}
 */
export function makeAdmin()
{
    return {
        name:     "Installation Test",
        email:    "installtest@example.com",
        username: "installtest",
        password: `Pn-${randomBytes(18).toString("base64url")}-9z!`,
    };
}

/**
 * Welcome page: pick the run's language in the selector, prove the interface switched, continue to the precheck.
 */
export async function chooseLanguage(page, ctx)
{
    const step = "choose language";

    await page.goto("/index.php");

    /**
     * Every install must start on the welcome page. Anything left over from a previous install (config.php, tables, a
     * session) sends the browser elsewhere and would let later steps pass without ever being tested.
     */
    if (!await page.locator("#language").isVisible())
    {
        throw new Error(`[${step}] The first page is not the welcome page with the language selector: ${page.url()}`);
    }

    let englishHeading = null;

    if (ctx.lang !== "en-GB")
    {
        await page.goto("/index.php?view=setup&language=en-GB");
        englishHeading = await welcomeHeading(page);
    }

    // The select submits its form on change (akeebaGridViewAutoSubmitOnChange), reloading with ?language=xx-XX.
    await navigate(page, () => page.locator("#language").selectOption(ctx.lang));
    await assertUrl(page, step, {language: ctx.lang});
    await assertLanguage(page, step, ctx.lang);

    if (englishHeading !== null && await welcomeHeading(page) === englishHeading)
    {
        throw new Error(`[${step}] The welcome heading is still the en-GB one ("${englishHeading}") after choosing `
            + `${ctx.lang}: the language did not switch.`);
    }

    await assertPageClean(page, {step, watcher: ctx.watcher});
    await navigate(page, () => page.locator("a.btn-primary[href*='task=precheck']").click());
    await assertUrl(page, step, {view: "setup", task: "precheck"});
    assertServerClean(step);
}

/**
 * Precheck page: requirements must be met; continue to the database page.
 */
export async function passPrecheck(page, ctx)
{
    const step = "precheck";

    // #systemsGo is only rendered when every requirement is met; a script then removes its d-none.
    if (!await page.locator("#systemsGo").count())
    {
        const details = await page.locator("#requirementsCheck").innerText().catch(() => "(not found)");

        throw new Error(`[${step}] The server does not meet the requirements:\n${details}`);
    }

    await page.locator("#systemsGo:not(.d-none)").waitFor();
    await assertPageClean(page, {step, watcher: ctx.watcher});
    await navigate(page, () => page.locator("#systemsGo a.btn-primary").click());
    await assertUrl(page, step, {view: "setup", task: "database"});
    assertServerClean(step);
}

/**
 * Database page: connect to the lab's database and install the schema; continue to the admin account page.
 */
export async function fillDatabase(page, ctx)
{
    const step = "database";

    await assertPageClean(page, {step, watcher: ctx.watcher});

    for (const [field, value] of Object.entries(DATABASE))
    {
        await page.locator(`#${field}`).fill(value);
    }

    await navigate(page, () => page.locator("#dbFormSubmit").click());
    await assertUrl(page, step, {view: "setup", task: "setup"});
    assertServerClean(step);
}

/**
 * The session page (FTP form) only shows when no session folder is writable, which no normal install hits. Render it
 * directly in a second tab of the same browser session and check its strings; nothing is submitted.
 */
export async function checkSessionPage(page, ctx)
{
    const step    = "session page";
    const tab     = await page.context().newPage();
    const watcher = watchPage(tab);

    try
    {
        await tab.goto("/index.php?view=setup&task=session&layout=session");
        await assertUrl(tab, step, {view: "setup", task: "session"});
        await tab.locator("#setupFormSubmit").waitFor();
        await assertLanguage(tab, step, ctx.lang);
        await assertPageClean(tab, {step, watcher});
    }
    finally
    {
        await tab.close();
    }

    assertServerClean(step);
}

/**
 * Admin account page: create the Super User. Saving also writes config.php and logs the new user in. Normally that
 * leads to the CRON page; when config.php cannot be written, to the manual "save configuration" page.
 *
 * @param {{next?: "cron"|"saveconfig"}} options  The task the form is expected to reach
 */
export async function createAdmin(page, ctx, {next = "cron"} = {})
{
    const step = "admin account";

    await assertPageClean(page, {step, watcher: ctx.watcher});

    const fields = {
        name:      ctx.admin.name,
        email:     ctx.admin.email,
        username:  ctx.admin.username,
        password:  ctx.admin.password,
        password2: ctx.admin.password,
    };

    for (const [field, value] of Object.entries(fields))
    {
        await page.locator(`#setupForm #${field}`).fill(value);
    }

    await navigate(page, () => page.locator("#setupForm button[type=submit]").click());

    // A saveconfig page has no redirect, so its URL keeps the form's task; a CRON page is reached by redirect.
    await assertUrl(page, step, {view: "setup", task: next});
    assertServerClean(step);
}

/**
 * CRON page: check it as it is, then skip the CRON setup and land on the dashboard.
 */
export async function skipCron(page, ctx)
{
    const step = "skip CRON";

    await page.locator("a[href*='task=skipcron']").waitFor();
    await assertPageClean(page, {step, watcher: ctx.watcher});
    await navigate(page, () => page.locator("a[href*='task=skipcron']").click());
    await assertUrl(page, step, {view: "main"});
    await checkDashboard(page, ctx, step);
}

/**
 * Log out through the user menu, log back in through the login form with the interface in the run's language, and
 * land on the dashboard.
 */
export async function logOutAndBackIn(page, ctx)
{
    const step   = "log out and back in";
    const logout = page.locator("a[href*='task=logout']").first();

    // The log-out link lives in the user menu's dropdown.
    await page.locator(".dropdown:has(a[href*='task=logout']) > [data-bs-toggle='dropdown']").first().click();
    await navigate(page, () => logout.click());
    await assertUrl(page, step, {view: "login"});

    /**
     * Logging out may or may not keep the language chosen on the welcome page. If it does not, switch it with the
     * login page's own selector, as a user would; either way the login page must end up in the run's language, or its
     * strings would be checked in English only.
     */
    if (await htmlLang(page) !== primaryTag(ctx.lang))
    {
        await navigate(page, () => page.locator("#language").selectOption(ctx.lang));
        await assertUrl(page, step, {view: "login"});
    }

    await assertLanguage(page, step, ctx.lang);

    if (ctx.lang !== "en-GB")
    {
        const englishHeading = await englishLoginHeading(page);
        const heading        = (await page.locator("#loginForm h3").innerText()).trim();

        if (heading === englishHeading)
        {
            throw new Error(`[${step}] The login heading is still the en-GB one ("${englishHeading}") in ${ctx.lang}.`);
        }
    }

    await assertPageClean(page, {step, watcher: ctx.watcher});
    await page.locator("#username").fill(ctx.admin.username);
    await page.locator("#password").fill(ctx.admin.password);
    await navigate(page, () => page.locator("#btnLoginSubmit").click());
    await assertUrl(page, step, {view: "main"});
    await checkDashboard(page, ctx, step);
}

/**
 * The logged-in dashboard, in the run's language, with nothing wrong on it.
 */
async function checkDashboard(page, ctx, step)
{
    const where = `${step}: dashboard`;

    // The dashboard loads parts of itself over AJAX; let those finish so their failures are counted here.
    await page.waitForLoadState("networkidle");
    await assertLanguage(page, where, ctx.lang);
    await assertPageClean(page, {step: where, watcher: ctx.watcher});
}

/**
 * Run an action which navigates and wait until the new document has loaded (after any redirects). Waiting on the
 * URL instead would return at once whenever the target URL matches the page we are leaving.
 */
async function navigate(page, action)
{
    await Promise.all([page.waitForEvent("load"), action()]);
}

/**
 * Assert the page's query string has the given parameters. On a mismatch, the message carries the page's error
 * banners, which is where the wizard explains why it sent the user back.
 */
async function assertUrl(page, step, expected)
{
    const url    = new URL(page.url());
    const actual = Object.fromEntries(Object.keys(expected).map((key) => [key, url.searchParams.get(key)]));

    // index.php without a view is the dashboard once logged in.
    if (expected.view === "main" && actual.view === null)
    {
        actual.view = "main";
    }

    if (Object.entries(expected).every(([key, value]) => actual[key] === value))
    {
        return;
    }

    const banners = (await page.locator(".alert-danger, .alert-error").allInnerTexts())
        .map((text) => text.trim()).filter(Boolean);

    throw new Error(`[${step}] Expected ${JSON.stringify(expected)}, but reached ${page.url()}`
        + (banners.length ? `\n  Messages on the page:\n  - ${banners.join("\n  - ")}` : ""));
}

/**
 * The page template sets `<html lang>` to the primary subtag of the interface language actually loaded.
 */
async function assertLanguage(page, step, lang)
{
    const actual = await htmlLang(page);

    if (actual !== primaryTag(lang))
    {
        throw new Error(`[${step}] The page is in "${actual}", not in ${lang}: ${page.url()}`);
    }
}

async function htmlLang(page)
{
    return page.locator("html").getAttribute("lang");
}

function primaryTag(lang)
{
    return lang.split("-")[0];
}

async function welcomeHeading(page)
{
    return (await page.locator("h3").first().innerText()).trim();
}

/**
 * The en-GB login heading, read in a separate browser context so the run's own session and language stay untouched.
 */
async function englishLoginHeading(page)
{
    const context = await page.context().browser().newContext();

    try
    {
        const english = await context.newPage();

        await english.goto(new URL("/index.php?view=login&lang=en-GB", page.url()).href);

        return (await english.locator("#loginForm h3").innerText()).trim();
    }
    finally
    {
        await context.close();
    }
}
