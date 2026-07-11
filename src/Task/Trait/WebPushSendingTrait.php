<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Model\Pushsubscriptions;
use Awf\Registry\Registry;
use Awf\Uri\Uri;

/**
 * A trait for enqueuing Web Push notifications alongside emails.
 *
 * @since  1.3.0
 */
trait WebPushSendingTrait
{
	/**
	 * Templates that should NOT trigger WebPush notifications.
	 *
	 * Summary emails, password resets, and registration emails are excluded
	 * because they are either too frequent, security-sensitive, or not relevant
	 * to browser notifications.
	 */
	private const EXCLUDED_TEMPLATES = [
		'scheduled_update_summary',
		'action_summary',
		'pwreset',
		'registration_pending_admin',
		'registration_notify_admin',
		'registration_activate',
		'registration_approved',
		'registration_expired',
	];

	/**
	 * Enqueue Web Push notifications for the same recipients as the email.
	 *
	 * @param   Registry  $data    The mail data registry (same as used for enqueueEmail)
	 * @param   int|null  $siteId  The site ID this notification refers to
	 * @param   int[]     $userIds Pre-resolved recipient user IDs (see RecipientResolver)
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function enqueueWebPush(Registry $data, ?int $siteId, array $userIds): void
	{
		$container = Factory::getContainer();

		$template = $data->get('template', '');

		// Skip excluded templates
		if (empty($template) || in_array($template, self::EXCLUDED_TEMPLATES, true))
		{
			return;
		}

		if (empty($userIds))
		{
			return;
		}

		// Get push subscriptions for all recipient users
		/** @var Pushsubscriptions $pushModel */
		$pushModel     = $container->mvcFactory->makeTempModel('Pushsubscriptions');
		$subscriptions = $pushModel->getSubscriptionsForUsers($userIds);

		if (empty($subscriptions))
		{
			return;
		}

		// Build the notification payload
		$lang = $container->language;

		$titleKey = 'PANOPTICON_WEBPUSH_TITLE_' . strtoupper($template);
		$bodyKey  = 'PANOPTICON_WEBPUSH_BODY_' . strtoupper($template);

		$title = $lang->text($titleKey);
		$body  = $lang->text($bodyKey);

		// If the language string is the key itself, use a generic fallback
		if ($title === $titleKey)
		{
			$title = $lang->text('PANOPTICON_WEBPUSH_TITLE_GENERIC');
		}

		if ($body === $bodyKey)
		{
			$body = $lang->text('PANOPTICON_WEBPUSH_BODY_GENERIC');
		}

		// Substitute site name if available
		$emailVars = (array) $data->get('email_variables', []);

		if (isset($emailVars['SITE_NAME']))
		{
			$body = str_replace('[SITE_NAME]', $emailVars['SITE_NAME'], $body);
		}

		if (isset($emailVars['SITENAME']))
		{
			$body = str_replace('[SITENAME]', $emailVars['SITENAME'], $body);
		}

		// Build the click URL
		$clickUrl = Uri::base();

		if ($siteId)
		{
			$clickUrl .= $container->router->route(
				sprintf('index.php?view=site&task=read&id=%d', $siteId)
			);
		}

		$payload = json_encode([
			'title' => $title,
			'body'  => $body,
			'icon'  => Uri::base() . 'media/images/logo-color.svg',
			'tag'   => $template . ($siteId ? '-' . $siteId : ''),
			'url'   => $clickUrl,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Enqueue one item per subscription
		$queue = $container->queueFactory->makeQueue(QueueTypeEnum::WEBPUSH->value);

		foreach ($subscriptions as $subscription)
		{
			$itemData = new Registry();
			$itemData->set('endpoint', $subscription->endpoint);
			$itemData->set('key_p256dh', $subscription->key_p256dh);
			$itemData->set('key_auth', $subscription->key_auth);
			$itemData->set('encoding', $subscription->encoding);
			$itemData->set('payload', $payload);
			$itemData->set('retries', 0);

			$queueItem = new QueueItem(
				$itemData->toString(),
				QueueTypeEnum::WEBPUSH->value,
				$siteId,
			);

			$queue->push($queueItem, 'now');
		}
	}
}
