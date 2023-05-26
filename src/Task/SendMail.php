<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;
use Awf\Timer\Timer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

#[AsTask(
	name: 'sendmail',
	description: 'PANOPTICON_TASKTYPE_SENDMAIL'
)]
class SendMail extends AbstractCallback implements LoggerAwareInterface
{
	use LoggerAwareTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);
		$timeLimit    = $params->get('timelimit', 60);
		$bias         = $params->get('bias', 75);

		$container = Factory::getContainer();
		$mailQueue = $container->queueFactory->makeQueue(QueueTypeEnum::MAIL->name);
		$timer     = new Timer($timeLimit, $bias);

		while ($queueItem = $mailQueue->pop())
		{
			// Get the parameters for sending an email
			$sendingParams = new Registry($queueItem->getData() ?? '{}');
			$template      = $sendingParams->get('template');
			$language      = $sendingParams->get('language', 'en-GB');
			$variables     = $sendingParams->get('email_variables', []);
			$permissions   = $sendingParams->get('permissions');
			$cc            = $sendingParams->get('email_cc');

			if (empty($template))
			{
				$this->logger->error(
					sprintf(
						'Cannot send email template %s for site %d; mail template not found',
						$template, $queueItem->getSiteId()
					)
				);

				continue;
			}

			$this->logger->info(
				sprintf(
					'Sending email template %s for site %d',
					$template, $queueItem->getSiteId()
				)
			);

			$mailer = clone $container->mailer;
			$mailer->initialiseWithTemplate($template, $language, (array) $variables);

			if (empty($mailer->Body))
			{
				$this->logger->debug(
					sprintf(
						'Not sending email template %s for site %d; mail template empty',
						$template, $queueItem->getSiteId()
					)
				);

				continue;
			}

			$recipients = $this->getRecipientsByPermissions($permissions);

			if (empty($recipients))
			{
				$this->logger->debug(
					sprintf(
						'Not sending email template %s for site %d; no recipients',
						$template, $queueItem->getSiteId()
					)
				);
				continue;
			}

			foreach ($recipients as $recipient)
			{
				[$email, $name] = $recipient;

				$mailer->addRecipient($email, $name);
			}

			// Add CC'ed users by configuration
			foreach ($cc as $recipient)
			{
				[$email, $name] = $recipient;

				$mailer->addCC($email, $name);
			}

			// Send the email
			try
			{
				$mailer->Send();

				$this->logger->debug(
					sprintf(
						'Sent email template %s for site %d',
						$template, $queueItem->getSiteId()
					)
				);
			}
			catch (\PHPMailer\PHPMailer\Exception $e)
			{
				$this->logger->error(
					sprintf(
						'Not sent email template %s for site %d: %s',
						$template, $queueItem->getSiteId(), $e->getMessage()
					)
				);
			}

			// Check for timeout
			if ($timer->getTimeLeft() <= 0)
			{
				break;
			}
		}

		return Status::OK->value;
	}

	private function getRecipientsByPermissions(array $permissions): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('name'),
				$db->quoteName('email'),
			])
			->from($db->quoteName('#__users'));

		foreach ($permissions as $permission)
		{
			$query
				->where(
					$query->jsonPointer('parameters', '$.acl.' . $permission) . ' = TRUE',
					'OR'
				);
		}

		return array_map(
			fn(object $o) => [$o->email, $o->name],
			$db->setQuery($query)->loadObjectList() ?: []
		);
	}

}