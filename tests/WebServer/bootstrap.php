<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * Bootstrap for the web-server MCP integration test tier.
 *
 * This tier talks to a live Dockerised Apache + PHP container over HTTP — it does NOT touch the
 * test database, so unlike tests/bootstrap.php it does not boot the Container, load a schema, or
 * enforce the production-DB guard. It only needs the Composer autoloader (for Guzzle) and the
 * AKEEBA guard constant.
 */

declare(strict_types=1);

define('AKEEBA', 1);

require_once __DIR__ . '/../../vendor/autoload.php';
