<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;

trait SaveSiteTrait
{
	private function saveSite(Site $site, callable $updater, ?callable $errorHandler = null)
	{
		/**
		 * Reasoning behind this code:
		 *
		 * “The correct way to use LOCK TABLES and UNLOCK TABLES with transactional tables, such as InnoDB tables, is to
		 * begin a transaction with SET autocommit = 0 (not START TRANSACTION) followed by LOCK TABLES, and to not call
		 * UNLOCK TABLES until you commit the transaction explicitly.”
		 *
		 * This is meant to avoid deadlocks.
		 *
		 * @see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		 */
		$db = Factory::getContainer()->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__sites');

		try
		{
			// Reload the site, in case something changed in the meantime
			$tempSite = $site->getClone()->reset(true, true)->findOrFail($site->getId());

			call_user_func($updater, $tempSite);

			// Save the configuration (three tries)
			$retry = -1;

			do
			{
				try
				{
					$retry++;

					$tempSite->save();

					break;
				}
				catch (\Exception $e)
				{
					if ($retry >= 3)
					{
						throw $e;
					}

					sleep($retry);
				}
			} while ($retry < 3);
		}
		catch (\Throwable $e)
		{
			if (is_callable($errorHandler))
			{
				$errorHandler($e);
			}
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
		}

		$site->bind($tempSite->getData());
	}
}