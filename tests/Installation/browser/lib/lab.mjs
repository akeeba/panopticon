/**
 * Host-side control of the installation lab (tests/Installation/docker): resetting it between installs, reading its
 * server logs and running commands in its php container. Everything goes through `docker compose exec`, because the
 * web root lives only inside the container; nothing is bind-mounted from the host.
 *
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */
import {execFileSync} from "node:child_process";
import {fileURLToPath} from "node:url";

const composeFile = process.env.INSTALLTEST_COMPOSE_FILE
    ?? fileURLToPath(new URL("../../docker/compose.yml", import.meta.url));
const project     = process.env.INSTALLTEST_PROJECT ?? "panopticon_installtest";

const PHP_LOG    = "/var/log/php/error.log";
const APACHE_LOG = "/var/log/apache2/error.log";
const CRON_PID   = "/tmp/installtest-cron.pid";

/**
 * Apache error-log lines which count as a failure: level error or worse (`[core:error]`, `[proxy_fcgi:crit]`, …), or
 * anything PHP-FPM passed through (`AH01071: Got error 'PHP message: …'`), whatever its level.
 */
const APACHE_PROBLEM = /\[[\w-]*:(error|crit|alert|emerg)\]|PHP message/;

function compose(args, options = {})
{
    return execFileSync("docker", ["compose", "-f", composeFile, "-p", project, ...args], {
        encoding: "utf8",
        stdio:    [options.input === undefined ? "ignore" : "pipe", "pipe", "pipe"],
        ...options,
    });
}

/**
 * Run a command in the php container (`docker compose exec -T php …`) and return its standard output.
 *
 * @param {string[]} args  The command and its arguments
 * @returns {string}
 */
export function exec(args)
{
    return compose(["exec", "-T", "php", ...args]);
}

/**
 * Fresh database, freshly unzipped release ZIP, empty logs. With `readonly` the web root belongs to root, so PHP
 * cannot write config.php (only cache/, log/ and tmp/ stay writable).
 *
 * @param {{readonly?: boolean}} options
 */
export function resetLab({readonly = false} = {})
{
    // A CRON loop left behind by a failed test would run task:run against the new install. A no-op if none runs.
    stopCronJob();

    const output = exec(["php", "/lab/lab-reset.php", ...(readonly ? ["--readonly"] : [])]);

    if (!output.includes("RESET OK"))
    {
        throw new Error(`The lab reset did not confirm success. Its output was:\n${output}`);
    }
}

/**
 * Every line of the PHP error log, plus the Apache error-log lines which point to a server-side failure. The logs are
 * emptied by resetLab(), so this is everything since the current install started.
 *
 * @returns {string[]}
 */
export function readServerProblems()
{
    const read = (file) => exec(["cat", file]).split("\n").map((line) => line.trim()).filter(Boolean);

    return [
        ...read(PHP_LOG).map((line) => `PHP log: ${line}`),
        ...read(APACHE_LOG).filter((line) => APACHE_PROBLEM.test(line)).map((line) => `Apache log: ${line}`),
    ];
}

/**
 * The complete PHP error log and Apache error log of the current install, for attaching to a failed test.
 *
 * @returns {{php: string, apache: string}}
 */
export function readRawServerLogs()
{
    const read = (file) =>
    {
        try
        {
            return exec(["cat", file]);
        }
        catch (error)
        {
            return `(could not read ${file}: ${error.message})`;
        }
    };

    return {php: read(PHP_LOG), apache: read(APACHE_LOG)};
}

/**
 * Write /var/www/html/config.php as root with mode 0644, the way a user uploads it by FTP when PHP cannot write it.
 *
 * @param {string} phpSource  The file's contents
 */
export function writeConfig(phpSource)
{
    compose([
        "exec", "-T", "-u", "root", "php", "sh", "-c",
        "cat > /var/www/html/config.php && chown root:root /var/www/html/config.php"
        + " && chmod 0644 /var/www/html/config.php",
    ], {input: phpSource});
}

/**
 * Start a CRON job emulation in the php container: `task:run` once a minute, as the web server user, in the web root,
 * like the crontab line the CRON page tells users to add. It runs detached until stopCronJob().
 */
export function startCronJob()
{
    // `exec -d` cannot report anything the detached loop says, so refuse a second loop here, before starting one.
    const running = exec(["sh", "-c", `[ -f ${CRON_PID} ] && kill -0 "$(cat ${CRON_PID})" 2>/dev/null && echo yes`
        + ` || { rm -f ${CRON_PID}; echo no; }`]).trim();

    if (running === "yes")
    {
        throw new Error("The CRON job emulation is already running; call stopCronJob() first.");
    }

    /**
     * Each command runs in the background and the loop `wait`s for it, so that a TERM interrupts the wait at once and
     * the trap can kill whatever is running (a task:run or the sleep). A plain foreground `sleep 60` would delay the
     * signal until it ended, and killing only the shell would leave an orphaned task:run behind.
     */
    const loop = [
        `echo $$ > ${CRON_PID}`,
        "child=",
        `trap 'kill $child 2>/dev/null; rm -f ${CRON_PID}; exit 0' TERM INT`,
        "while true; do",
        "  php cli/panopticon.php task:run & child=$!; wait $child",
        "  sleep 60 & child=$!; wait $child",
        "done",
    ].join("\n");

    compose(["exec", "-d", "-u", "www-data", "-w", "/var/www/html", "php", "sh", "-c", loop]);

    // `exec -d` returns before the loop has started; wait (on state, with a bound) until it has recorded its PID.
    exec(["sh", "-c", `for i in $(seq 1 100); do [ -s ${CRON_PID} ] && exit 0; sleep 0.1; done; exit 1`]);
}

/**
 * Stop the CRON job emulation started by startCronJob() and wait until it, and whatever it was running, has exited.
 * Does nothing if it is not running.
 */
export function stopCronJob()
{
    exec(["sh", "-c", [
        `[ -f ${CRON_PID} ] || exit 0`,
        `pid=$(cat ${CRON_PID})`,
        "kill -TERM $pid 2>/dev/null || { rm -f " + CRON_PID + "; exit 0; }",
        "for i in $(seq 1 100); do kill -0 $pid 2>/dev/null || exit 0; sleep 0.1; done",
        // Did not stop within 10 seconds: force it, children first (they are the loop's direct descendants).
        "for child in $(grep -l \"^PPid:[[:space:]]*$pid$\" /proc/[0-9]*/status 2>/dev/null"
        + " | cut -d/ -f3); do kill -KILL $child 2>/dev/null; done",
        `kill -KILL $pid 2>/dev/null; rm -f ${CRON_PID}`,
    ].join("\n")]);
}
