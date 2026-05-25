<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api;

defined('AKEEBA') || die;

/**
 * Thrown by the test stub Application::close() to unwind the handler without calling exit().
 *
 * @since  1.4.0
 */
class ApiResponseException extends \RuntimeException
{
	public readonly int $exitCode;

	public function __construct(int $exitCode = 0)
	{
		$this->exitCode = $exitCode;
		parent::__construct('API handler terminated (test).');
	}
}
