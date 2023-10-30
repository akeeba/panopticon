<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Exception\Configuration;

defined('AKEEBA') || die;

use RuntimeException;
use Throwable;

/**
 * The
 *
 * @since 1.0.2
 */
class ReadOnlyRepository extends RuntimeException
{
	public function __construct(?Throwable $previous = null)
	{
		$message = "The Panopticon configuration repository is read-only. This means that you are using .env files, therefore the config.php file is ignored. Please edit your .env files instead.";

		parent::__construct($message, 500, $previous);
	}

}