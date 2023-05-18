<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Exception;

defined('AKEEBA') || die;

use Awf\Text\Text;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;

class AccessDenied extends RuntimeException
{
	#[Pure] public function __construct(string $message = "", ?Throwable $previous = null)
	{
		$message = $message ?: Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN');

		parent::__construct($message, 403, $previous);
	}
}