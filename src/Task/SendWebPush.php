<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Pushsubscriptions;
use Awf\Registry\Registry;
use Awf\Timer\Timer;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

#[AsTask(
	name: 'sendwebpush',
	description: 'PANOPTICON_TASKTYPE_SENDWEBPUSH'
)]
class SendWebPush extends AbstractCallback
{
	private const MAX_RETRIES = 3;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params    = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);
		$timeLimit = $params->get('timelimit', 60);
		$bias      = $params->get('bias', 75);

		$queue = $this->container->queueFactory->makeQueue(QueueTypeEnum::WEBPUSH->value);
		$timer = new Timer($timeLimit, $bias);

		$vapidHelper = $this->container->vapidHelper;
		$auth        = [
			'VAPID' => [
				'subject'    => 'mailto:' . ($this->container->appConfig->get('mail_from', 'noreply@example.com')),
				'publicKey'  => $vapidHelper->getPublicKey(),
				'privateKey' => $vapidHelper->getPrivateKey(),
			],
		];

		$webPush = new WebPush($auth);

		/** @var Pushsubscriptions $pushModel */
		$pushModel = $this->container->mvcFactory->makeTempModel('Pushsubscriptions');

		while ($queueItem = $queue->pop())
		{
			$itemData = new Registry($queueItem->getData() ?? '{}');
			$endpoint = $itemData->get('endpoint', '');
			$p256dh   = $itemData->get('key_p256dh', '');
			$authKey  = $itemData->get('key_auth', '');
			$encoding = $itemData->get('encoding', 'aesgcm');
			$payload  = $itemData->get('payload', '');
			$retries  = (int) $itemData->get('retries', 0);

			if (empty($endpoint) || empty($p256dh) || empty($authKey) || empty($payload))
			{
				$this->logger->warning('Skipping WebPush queue item with missing data');
				continue;
			}

			$subscription = Subscription::create([
				'endpoint'        => $endpoint,
				'publicKey'       => $p256dh,
				'authToken'       => $authKey,
				'contentEncoding' => $encoding,
			]);

			try
			{
				$report = $webPush->sendOneNotification($subscription, $payload);
			}
			catch (\Throwable $e)
			{
				$this->logger->error(
					sprintf('WebPush send error for endpoint %s: %s', substr($endpoint, 0, 80), $e->getMessage())
				);

				$this->handleFailure($queue, $itemData, $retries, $pushModel, $endpoint);

				if ($timer->getTimeLeft() <= 0)
				{
					break;
				}

				continue;
			}

			if ($report->isSuccess())
			{
				$this->logger->debug(
					sprintf('WebPush sent successfully to endpoint %s', substr($endpoint, 0, 80))
				);
			}
			else
			{
				$statusCode = $report->getResponse()?->getStatusCode() ?? 0;

				$this->logger->warning(
					sprintf(
						'WebPush failed for endpoint %s: [%d] %s',
						substr($endpoint, 0, 80),
						$statusCode,
						$report->getReason()
					)
				);

				// 404 or 410: subscription no longer valid, remove it
				if ($statusCode === 404 || $statusCode === 410)
				{
					$pushModel->removeByEndpoint($endpoint);
				}
				else
				{
					$this->handleFailure($queue, $itemData, $retries, $pushModel, $endpoint);
				}
			}

			if ($timer->getTimeLeft() <= 0)
			{
				break;
			}
		}

		return Status::OK->value;
	}

	/**
	 * Handle a failed WebPush delivery by re-enqueueing or removing the subscription.
	 *
	 * @param   object              $queue      The queue instance
	 * @param   Registry            $itemData   The queue item data
	 * @param   int                 $retries    The current retry count
	 * @param   Pushsubscriptions   $pushModel  The push subscriptions model
	 * @param   string              $endpoint   The subscription endpoint
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function handleFailure(
		object $queue,
		Registry $itemData,
		int $retries,
		Pushsubscriptions $pushModel,
		string $endpoint
	): void
	{
		if ($retries >= self::MAX_RETRIES)
		{
			$this->logger->warning(
				sprintf('Removing subscription %s after %d failed retries', substr($endpoint, 0, 80), $retries)
			);
			$pushModel->removeByEndpoint($endpoint);

			return;
		}

		// Re-enqueue with incremented retry count
		$itemData->set('retries', $retries + 1);

		$retryItem = new QueueItem(
			$itemData->toString(),
			QueueTypeEnum::WEBPUSH->value,
			null,
		);

		$queue->push($retryItem, 'now');
	}
}
