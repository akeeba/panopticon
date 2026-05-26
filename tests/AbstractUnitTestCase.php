<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests;

defined('AKEEBA') || die;

use PHPUnit\Framework\TestCase;

/**
 * Base class for unit tests.
 *
 * Unit tests must not touch the database. This base class deliberately omits any DB transaction
 * wrapping. If you find yourself needing the container or the DB, extend
 * {@see AbstractIntegrationTestCase} instead and place the test under tests/Integration.
 *
 * @since 1.4.0
 */
abstract class AbstractUnitTestCase extends TestCase
{
}
