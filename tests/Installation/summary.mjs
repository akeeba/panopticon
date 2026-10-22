/**
 * Summarises the Playwright JSON reports written by run.sh, one per combination:
 *
 *   node summary.mjs results/<slug>/report.json …
 *
 * Prints one line per failed test (combination, language, path and the test.step it failed in) and the totals. The
 * exit code is 1 if anything failed, was interrupted, or if there was no report to read at all.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {readFileSync} from "node:fs";

const files = process.argv.slice(2);

if (!files.length)
{
    console.error("No report.json files: nothing ran, so nothing passed.");
    process.exit(1);
}

/**
 * Every spec of a suite tree, depth first.
 */
function* specsOf(suite)
{
    for (const spec of suite.specs ?? [])
    {
        yield spec;
    }

    for (const child of suite.suites ?? [])
    {
        yield* specsOf(child);
    }
}

/**
 * The title of the first step (at any depth) which carries an error.
 */
function failedStep(steps)
{
    for (const step of steps ?? [])
    {
        const inner = failedStep(step.steps);

        if (inner)
        {
            return inner;
        }

        if (step.error)
        {
            return step.title;
        }
    }

    return null;
}

const firstLine = (text) => String(text ?? "").replace(/\u001b\[[0-9;]*m/g, "").split("\n").find((line) => line.trim());

let passed = 0;
let skipped = 0;
const failures = [];

for (const file of files)
{
    let report;

    try
    {
        report = JSON.parse(readFileSync(file, "utf8"));
    }
    catch (error)
    {
        failures.push(`${file}: cannot read the report (${error.message})`);
        continue;
    }

    for (const error of report.errors ?? [])
    {
        failures.push(`${file}: ${firstLine(error.message)}`);
    }

    for (const suite of report.suites ?? [])
    {
        for (const spec of specsOf(suite))
        {
            for (const test of spec.tests ?? [])
            {
                const result = test.results?.[test.results.length - 1];
                const status = result?.status ?? "unknown";

                if (status === "passed")
                {
                    passed++;
                    continue;
                }

                if (status === "skipped")
                {
                    skipped++;
                    continue;
                }

                // The spec titles its tests "<combination> · <language> · <path>".
                const parts       = spec.title.split(" · ");
                const path        = parts.length > 2 ? parts.pop() : "?";
                const lang        = parts.length > 1 ? parts.pop() : "?";
                const combination = parts.join(" · ") || spec.title;
                const message     = firstLine(result?.errors?.[0]?.message);
                // Our assertions word their errors "[step] url", which covers a failure outside any test.step.
                const step        = failedStep(result?.steps) ?? message?.match(/^\[([^\]]+)\]/)?.[1] ?? "(no step)";

                failures.push(`${combination} · ${lang} · ${path} · failed in "${step}" (${status}): ${message ?? ""}`);
            }
        }
    }
}

if (failures.length)
{
    console.log(`\nFAILED (${failures.length}):`);

    for (const failure of failures)
    {
        console.log(`  - ${failure}`);
    }
}

console.log(`\n${passed} passed, ${failures.length} failed${skipped ? `, ${skipped} skipped` : ""}.`);

process.exit(failures.length || !passed ? 1 : 0);
