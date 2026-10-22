/**
 * Playwright configuration of the new-installation browser test. run.sh sets the environment and the output
 * directories per database / PHP combination; see specs/install.spec.mjs for the variables it reads.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {defineAkeebaConfig} from "../../../../buildfiles/playwright/lib/index.mjs";

export default defineAkeebaConfig({
    baseURL:  process.env.INSTALLTEST_BASE_URL,
    testDir:  "specs",
    // A whole install, network round-trips to the database and Have I Been Pwned included, per test.
    timeout:  5 * 60 * 1000,
    reporter: [
        ["list"],
        ["html", {open: "never", outputFolder: "report"}],
        ["json", {outputFile: "results/report.json"}],
    ],
});
