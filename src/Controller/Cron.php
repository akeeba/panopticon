<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\HttpResponseCodeTrait;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Controller;
use Awf\Timer\Timer;
use Throwable;

class Cron extends Controller
{
	use HttpResponseCodeTrait;

	public function main(): void
	{
		$logger = $this->container->loggerFactory->get('webcron');

		$httpCode = 200;
		$message  = null;

		try
		{
			$appConfig = $this->container->appConfig;

			$known = $appConfig->get('webcron_key', '');
			$user  = $this->input->get('key', '', 'raw');

			if (empty($known) || !hash_equals($known, $user))
			{
				throw new \RuntimeException('Invalid request', 403);
			}

			// Mark our last execution time
			$db = $this->container->db;
			$db->lockTable('#__akeeba_common');
			$query = $db->getQuery(true)
				->replace($db->quoteName('#__akeeba_common'))
				->values(implode(',', [
					$db->quote('panopticon.task.last.execution'),
					$db->quote($this->container->dateFactory()->toSql()),
				]));
			$db->setQuery($query)->execute();
			$db->unlockTables();

			/**
			 * @var  Task $model The Task model.
			 *
			 * IMPORTANT! We deliberately use the PHP 5.x / 7.x calling convention.
			 *
			 * Using the PHP 8.x and later calling convention with named parameters does not allow graceful termination on older
			 * PHP versions.
			 */
			$model = $this->getModel('Task');

			$timer = new Timer(
				$appConfig->get('max_execution', 60),
				$appConfig->get('execution_bias', 75)
			);

			while ($timer->getTimeLeft() > 0.01)
			{
				if (!$model->runNextTask($logger))
				{
					break;
				}
			}
		}
		catch (Throwable $e)
		{
			$httpCode = $e->getCode();

			if (!in_array($httpCode, [
				200, 400, 401, 403, 404, 406, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 425, 428, 429, 431,
				451, 500, 501, 502, 503, 504, 505, 506, 510, 511,
			]))
			{
				$httpCode = 500;
			}

			$message = $e->getMessage();
		}


		@ob_end_clean();
		header(sprintf('HTTP/1.1 %d %s', $httpCode, $this->httpCodeToMessage($httpCode)));
		header('Content-type: text/plain');
		header('Connection: close');

		if ($message)
		{
			echo $message;
		}

		flush();

		$this->container->application->close();
	}
}