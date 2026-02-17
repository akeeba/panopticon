<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api;

defined('AKEEBA') || die;

/**
 * Interface for API handler classes.
 *
 * Each handler corresponds to a single API endpoint (URL + HTTP method).
 *
 * @since  1.4.0
 */
interface ApiHandlerInterface
{
	/**
	 * Handle the API request and send the JSON response.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function handle(): void;
}
