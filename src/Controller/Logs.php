<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;

/**
 * Log management controller
 *
 * @since  1.0.0
 */
class Logs extends Controller
{
	/**
	 * Main page: list the log files, paginated
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function main()
	{
		$this->display();
	}

	/**
	 * Read a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function read(): void
	{
		// TODO
	}

	/**
	 * Delete a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function delete(): void
	{
		// TODO
	}

	/**
	 * Download a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function download(): void
	{
		// TODO
	}
}