<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Exception;


use JetBrains\PhpStorm\Pure;
use Throwable;

defined('AKEEBA') || die;

class AkeebaBackupCannotConnectException extends AkeebaBackupException
{
	#[Pure] public function __construct(string $message = "", int $code = 500, ?Throwable $previous = null)
	{
		$message = $message ?: "Cannot find a way to connect to your site's Akeeba Backup installation.";

		parent::__construct($message, $code, $previous);
	}

}