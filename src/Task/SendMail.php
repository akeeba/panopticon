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
use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;
use Awf\Timer\Timer;
use Awf\Utils\ArrayHelper;

#[AsTask(
	name: 'sendmail',
	description: 'PANOPTICON_TASKTYPE_SENDMAIL'
)]
class SendMail extends AbstractCallback
{
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

			$site = Site::getTmpInstance('', 'Site', $this->container);

			try
			{
				$site->findOrFail($queueItem->getSiteId());
			}
			catch (\Exception $e)
			{
				$site = null;
			}

			$recipients = $this->getRecipientsByPermissions($permissions, $site);

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

	private function getRecipientsByPermissions(array $permissions, ?Site $site = null): array
	{
		$db = $this->container->db;

		// If we have a site we need to find which groups it belongs to
		$groupIDs = [];

		if (!empty($site))
		{
			$groupIDs = $site->getConfig()->get('config.groups', []);
			$groupIDs = is_array($groupIDs) ? $groupIDs : [];
			$groupIDs = array_filter(ArrayHelper::toInteger($groupIDs));
		}

		// If we have groups, we need to find which of these groups fulfill the permissions requested
		if ($groupIDs)
		{
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__groups'))
				->where($db->quoteName('id') . ' IN(' . implode(',', $groupIDs) . ')');

			$query->andWhere(
				array_map(
					fn($permission) => 'JSON_SEARCH(' . $db->quoteName('privileges') . ', ' . $db->quote('one') . ',' .
						$db->quote($permission) . ')',
					$permissions
				)
			);

			$groupIDs = $db->setQuery($query)->loadColumn();
		}

		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('name'),
					$db->quoteName('email'),
				]
			)
			->from($db->quoteName('#__users'));

		foreach ($permissions as $permission)
		{
			$query
				->where(
					$query->jsonExtract($db->quoteName('parameters'), '$.acl.' . $permission) . ' = TRUE',
					'OR'
				);
		}

		foreach ($groupIDs as $groupID)
		{
			$query
				->where(
					$query->jsonContains(
						$db->quoteName('parameters'),
						$db->quote((string) $groupID),
						$db->quote('$.usergroups')
					), 'OR'
				);
		}

		return array_map(
			fn(object $o) => [$o->email, $o->name],
			$db->setQuery($query)->loadObjectList() ?: []
		);
	}

}