<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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

		$mailQueue = $this->container->queueFactory->makeQueue(QueueTypeEnum::MAIL->name);
		$timer     = new Timer($timeLimit, $bias);

		while ($queueItem = $mailQueue->pop())
		{
			// Get the parameters for sending an email
			$sendingParams    = new Registry($queueItem->getData() ?? '{}');
			$template         = $sendingParams->get('template');
			$fallbackLanguage = $sendingParams->get('language', 'en-GB');
			$variables        = $sendingParams->get('email_variables', []);
			$variablesByLang  = (array) $sendingParams->get('email_variables_by_lang', []);
			$permissions      = $sendingParams->get('permissions', []) ?? [];
			$permissions      = is_array($permissions) ? $permissions : [];
			$recipientId      = $sendingParams->get('recipient_id', null);
			$cc               = $sendingParams->get('email_cc');
			$cc               = is_array($cc) ? $cc : array_filter(array_map('trim', explode(',', $cc)));
			$mailGroups       = $sendingParams->get('email_groups', null);
			$mailGroups       = empty($mailGroups) ? null : array_filter(ArrayHelper::toInteger($mailGroups));
			$onlyMailGroups   = (bool) $sendingParams->get('only_email_groups', false);

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

			// Get the site object
			/** @var Site $site */
			$site = $this->container->mvcFactory->makeTempModel('Site');

			try
			{
				$site->findOrFail($queueItem->getSiteId());
			}
			catch (\Exception $e)
			{
				$site = null;
			}

			if ($recipientId)
			{
				$user = Factory::getContainer()->userManager->getUser($recipientId);

				if ($user?->getId() != $recipientId)
				{
					continue;
				}

				$recipients = [
					[$user->getEmail(), $user->getName(), $user->getParameters()->toString()]
				];
			}
			else
			{
				$recipients =
					$onlyMailGroups
						? $this->getRecipientsByPermissions([], null, $mailGroups)
						: $this->getRecipientsByPermissions($permissions, $site, $mailGroups);

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
			}

			// Distribute recipients by language
			$defaultLanguage      = $fallbackLanguage
				?: $this->getContainer()->appConfig->get('language', 'en-GB')
					?: 'en-GB';
			$recipientsByLanguage = [
				// Carbon Copied recipients always receive email in the default language
				'en-GB' => $cc,
			];

			foreach ($recipients as $recipient)
			{
				[$email, $name, $paramsJson] = $recipient;

				try
				{
					$params = json_decode($paramsJson ?? '{}', flags: JSON_THROW_ON_ERROR);
				}
				catch (\JsonException $e)
				{
					continue;
				}

				$language                          = $params?->language ?? $defaultLanguage;
				$recipientsByLanguage[$language]   ??= [];
				$recipientsByLanguage[$language][] = [$email, $name];
			}

			// Send emails by language
			$hasMultipleLanguages = count($recipientsByLanguage) > 1;

			foreach ($recipientsByLanguage as $language => $recipients)
			{
				if ($hasMultipleLanguages)
				{
					if (empty($language))
					{
						$language = $defaultLanguage;

						$this->logger->info(
							sprintf(
								'Sending email template %s for site %d and default language (%s)',
								$template, $queueItem->getSiteId(), $defaultLanguage
							)
						);
					}
					else
					{
						$this->logger->info(
							sprintf(
								'Sending email template %s for site %d and language %s',
								$template, $queueItem->getSiteId(), $language
							)
						);
					}
				}

				$varsForThisLang = (array) ($variablesByLang[$language] ?? []);
				$mailer          = clone $this->container->mailer;
				$mailer->initialiseWithTemplate(
					$template, $language, array_merge((array) $variables, $varsForThisLang)
				);

				if (empty($mailer->Body) && $language != 'en-GB' && $language != $defaultLanguage)
				{
					$this->logger->notice(
						sprintf(
							'Email template %s does not exist for language %s; I will retry using the default language (%s).',
							$template, $language, $defaultLanguage
						)
					);

					$language        = $defaultLanguage;
					$varsForThisLang = (array) ($variablesByLang[$language] ?? []);
					$mailer          = clone $this->container->mailer;
					$mailer->initialiseWithTemplate(
						$template, $language, array_merge((array) $variables, $varsForThisLang)
					);
				}

				if (empty($mailer->Body) && $language != 'en-GB' && $language == $defaultLanguage)
				{
					$this->logger->notice(
						sprintf(
							'Email template %s does not exist for the default language %s; I will retry using en-GB.',
							$template, $language
						)
					);

					$language        = 'en-GB';
					$varsForThisLang = (array) ($variablesByLang[$language] ?? []);
					$mailer          = clone $this->container->mailer;
					$mailer->initialiseWithTemplate(
						$template, $language, array_merge((array) $variables, $varsForThisLang)
					);
				}

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

				$firstRecipient = true;

				foreach ($recipients as $recipient)
				{
					[$email, $name] = $recipient;

					if ($firstRecipient)
					{
						$firstRecipient = false;
						$mailer->addRecipient($email, $name);
					}
					else
					{
						$mailer->addBCC($email, $name);
					}
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
				catch (\Throwable $e)
				{
					$this->logger->error(
						sprintf(
							'Not sent email template %s for site %d: %s',
							$template, $queueItem->getSiteId(), $e->getMessage()
						)
					);
				}
			}

			// Check for timeout
			if ($timer->getTimeLeft() <= 0)
			{
				break;
			}
		}

		return Status::OK->value;
	}

	private function getRecipientsByPermissions(array $permissions, ?Site $site = null, ?array $mailGroups = null
	): array
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
					fn($permission) => 'JSON_SEARCH(' . $db->quoteName('privileges') . ', ' . $db->quote('one') . ',' . $db->quote($permission) . ')',
					$permissions
				)
			);

			$groupIDs = $db->setQuery($query)->loadColumn();
		}

		// Combine all groups
		$groupIDs = array_unique(array_merge($groupIDs, $mailGroups ?? []));

		// Get the query
		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('name'),
					$db->quoteName('email'),
					$db->quoteName('parameters'),
				]
			)
			->from($db->quoteName('#__users'));

		// Look for permissions...
		foreach ($permissions as $permission)
		{
			$query
				->where(
					$query->jsonExtract($db->quoteName('parameters'), '$.acl.' . $permission) . ' = TRUE',
					'OR'
				);
		}

		// ...or any of the group IDs
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
			fn(object $o) => [$o->email, $o->name, $o->parameters],
			$db->setQuery($query)->loadObjectList() ?: []
		);
	}

}