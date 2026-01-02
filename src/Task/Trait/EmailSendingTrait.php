<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

defined('AKEEBA') || die;

/**
 * A trait for sending email messages
 *
 * @since  1.0.5
 */
trait EmailSendingTrait
{
	/**
	 * Enqueue an email message, and possibly send it immediately if the application is so configured.
	 *
	 * @param   Registry                   $data    The mail data to add to the mail queue item.
	 * @param   int|null                   $siteId  The ID of the site this email refers to (NULL if system-level)
	 * @param   \DateTime|int|string|null  $whence  When to send the email. Default: 'now'
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	private function enqueueEmail(Registry $data, ?int $siteId, \DateTime|int|string|null $whence = 'now')
	{
		$container = Factory::getContainer();

		// Create a mail queue item
		$queueItem = new QueueItem(
			$data->toString(),
			QueueTypeEnum::MAIL->value,
			$siteId,
		);

		// Push the item to the mail queue
		$container
			->queueFactory
			->makeQueue(QueueTypeEnum::MAIL->value)
			->push($queueItem, $whence);

		// Do I need to send emails right away?
		if (!$container->appConfig->get('immediate_email', 1))
		{
			return;
		}

		// Run the sendmail task to send all emails right away
		$callback = $container->taskRegistry->get('sendmail');
		$dummy1   = new \stdClass();
		$dummy2   = new Registry();

		do
		{
			$return = $callback($dummy1, $dummy2);
		} while ($return === Status::WILL_RESUME);
	}
}