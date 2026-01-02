<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Exception;

use JetBrains\PhpStorm\Pure;
use OutOfBoundsException;

class InvalidCronExpression extends OutOfBoundsException
{
	#[Pure] public function __construct(string $cronExpression = "", int $code = 500, ?\Throwable $previous = null)
	{
		$message = sprintf('Invalid CRON expression “%s”', $cronExpression);

		parent::__construct($message, $code, $previous);
	}

}