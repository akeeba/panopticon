<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
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