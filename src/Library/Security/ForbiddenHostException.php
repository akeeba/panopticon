<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Security;

defined('AKEEBA') || die;

use RuntimeException;
use Throwable;

/**
 * Thrown when an outbound HTTP request targets a host inside an operator-forbidden IP range.
 *
 * @since  1.4.0
 */
class ForbiddenHostException extends RuntimeException
{
	public function __construct(
		private readonly string $host,
		string                  $message = '',
		int                     $code = 0,
		?Throwable              $previous = null
	)
	{
		/**
		 * The message deliberately does not name the host.
		 *
		 * It surfaces to the end user through the Connection Doctor, and in the multi-tenant case that user is the
		 * one the policy exists to constrain. Naming the address would turn each attempt into an oracle for the
		 * operator's range list. The host remains available programmatically via getHost() for logging.
		 */
		$message = $message ?: 'The request target is not allowed on this installation.';

		parent::__construct($message, $code, $previous);
	}

	/**
	 * The host which was refused.
	 *
	 * @return  string
	 * @since   1.4.0
	 */
	public function getHost(): string
	{
		return $this->host;
	}
}
