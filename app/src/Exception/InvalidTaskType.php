<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Exception;

use JetBrains\PhpStorm\Pure;

class InvalidTaskType extends \OutOfRangeException
{
	#[Pure] public function __construct(string $type = "", int $code = 500, ?\Throwable $previous = null)
	{
		$message = sprintf('Unknown task type ‘%s’', $type);

		parent::__construct($message, $code, $previous);
	}

}