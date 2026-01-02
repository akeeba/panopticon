<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Exception\Configuration;

defined('AKEEBA') || die;

use RuntimeException;
use Throwable;

class UnwriteableRepository extends RuntimeException
{
	public function __construct(string $filePath, ?Throwable $previous = null)
	{
		$message = sprintf(
			'Cannot save configuration into %s; the file or folder is not writeable.', $filePath
		);

		parent::__construct($message, 500, $previous);
	}
}