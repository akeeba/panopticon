<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Exception;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;

class AccessDenied extends RuntimeException
{
	#[Pure] public function __construct(string $message = "", ?Throwable $previous = null)
	{
		$message = $message ?: Factory::getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN');

		parent::__construct($message, 403, $previous);
	}
}