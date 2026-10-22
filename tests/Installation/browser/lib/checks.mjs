/**
 * The checks every page of the installation must pass, in every language: no broken or missing translation, no PHP
 * error of any level, no client-side failure and no failed request.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {collectPageProblems, PHP_ERROR_PATTERN} from "../../../../../buildfiles/playwright/lib/index.mjs";
import {readServerProblems} from "./lab.mjs";

/**
 * Language keys which may legitimately appear as literal text (e.g. an environment variable name in instructions).
 * Every entry needs a comment naming the string and the page it is on. Add only what a green en-GB run proves
 * necessary: anything listed here is never reported, in any language.
 *
 * @type {string[]}
 */
export const RAW_KEY_ALLOWLIST = [];

const RAW_KEY = /\bPANOPTICON_[A-Z0-9_]+\b/g;

/**
 * Unlike the Joomla smoke tests, warnings, notices and deprecations count: this is Panopticon's own code.
 */
const PHP_DIAGNOSTIC = /(Warning|Notice|Deprecated):/;

/**
 * Start watching a page for problems. Wraps the shared collectPageProblems() watcher (JS exceptions, console errors,
 * failed sub-resource requests) and adds the one thing it deliberately leaves out: a document response of HTTP 400 or
 * above, e.g. a wizard step answering with an error page.
 *
 * @param {import("@playwright/test").Page} page
 * @returns {{take(): string[]}}
 */
export function watchPage(page)
{
    const shared    = collectPageProblems(page);
    const documents = [];

    page.on("response", (response) =>
    {
        if (response.status() >= 400 && response.request().resourceType() === "document")
        {
            documents.push(`HTTP ${response.status()} (document): ${response.url()}`);
        }
    });

    return {
        take()
        {
            return [...shared.take(), ...documents.splice(0)];
        },
    };
}

/**
 * Apply every page check to the page as it is now, plus the lab's server logs. Throws one error listing everything
 * found, with the step's name in the message so a report says where the installation broke.
 *
 * @param {import("@playwright/test").Page} page
 * @param {{step: string, watcher: {take(): string[]}}} options
 * @returns {Promise<void>}
 */
export async function assertPageClean(page, {step, watcher})
{
    const problems = [];
    const {text, title, attributes} = await page.evaluate(() =>
    {
        const attributeValues = [];

        for (const element of document.querySelectorAll("[alt], [title], [placeholder], [aria-label]"))
        {
            for (const name of ["alt", "title", "placeholder", "aria-label"])
            {
                if (element.hasAttribute(name))
                {
                    attributeValues.push(`${name}="${element.getAttribute(name)}"`);
                }
            }
        }

        // A button's caption may be its value attribute instead of its content.
        for (const element of document.querySelectorAll(
            "button[value], input[type=submit][value], input[type=button][value], input[type=reset][value]"
        ))
        {
            attributeValues.push(`value="${element.getAttribute("value")}"`);
        }

        return {text: document.body?.innerText ?? "", title: document.title, attributes: attributeValues};
    });

    const where = [["page text", text], ["page title", title], ["attributes", attributes.join("\n")]];

    for (const [label, content] of where)
    {
        if (content.includes("BAD TRANSLATION"))
        {
            problems.push(`BAD TRANSLATION in the ${label}: ${excerpt(content, "BAD TRANSLATION")}`);
        }

        const rawKeys = [...new Set(content.match(RAW_KEY) ?? [])].filter((key) => !RAW_KEY_ALLOWLIST.includes(key));

        if (rawKeys.length)
        {
            problems.push(`Raw language key(s) in the ${label}: ${rawKeys.join(", ")}`);
        }
    }

    for (const pattern of [PHP_ERROR_PATTERN, PHP_DIAGNOSTIC])
    {
        const match = text.match(pattern);

        if (match)
        {
            problems.push(`PHP error text on the page: ${excerpt(text, match[0])}`);
        }
    }

    problems.push(...watcher.take());
    problems.push(...readServerProblems());

    if (problems.length)
    {
        throw new Error(`[${step}] ${page.url()}\n  - ${problems.join("\n  - ")}`);
    }
}

/**
 * Assert the server logs are clean, naming the step. For steps whose last action leaves no page to check.
 *
 * @param {string} step
 */
export function assertServerClean(step)
{
    const problems = readServerProblems();

    if (problems.length)
    {
        throw new Error(`[${step}] server error log\n  - ${problems.join("\n  - ")}`);
    }
}

function excerpt(content, needle)
{
    const at = content.indexOf(needle);

    return JSON.stringify(content.slice(Math.max(0, at - 60), at + 160).replace(/\s+/g, " "));
}
