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
use Awf\Text\Language;
use Awf\Uri\Uri;
use JsonException;

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

		// The click URL and the email variables are the same regardless of the recipient's language.
		$emailVars = (array) $data->get('email_variables', []);
		$clickUrl  = Uri::base();

		if ($siteId)
		{
			$clickUrl .= $container->router->route(
				sprintf('index.php?view=site&task=read&id=%d', $siteId)
			);
		}

		// Bucket the subscriptions by the language of the user they belong to.
		$userLanguages   = $this->getRecipientLanguages($userIds);
		$defaultLanguage = $container->appConfig->get('language', 'en-GB') ?: 'en-GB';

		$subscriptionsByLanguage = [];

		foreach ($subscriptions as $subscription)
		{
			// NB! ?: not ??. A user who has not chosen a language has an empty string, not a missing key.
			$language = ($userLanguages[$subscription->user_id] ?? '') ?: $defaultLanguage;

			$subscriptionsByLanguage[$language]   ??= [];
			$subscriptionsByLanguage[$language][] = $subscription;
		}

		// Enqueue one item per subscription, its payload composed in the subscriber's own language.
		$queue           = $container->queueFactory->makeQueue(QueueTypeEnum::WEBPUSH->value);
		$languageObjects = [];

		foreach ($subscriptionsByLanguage as $language => $languageSubscriptions)
		{
			$languageObjects[$language] ??= $this->getLanguageObject($language);

			$payload = $this->getWebPushPayload(
				$languageObjects[$language], $template, $emailVars, $clickUrl, $siteId
			);

			foreach ($languageSubscriptions as $subscription)
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

	/**
	 * Get the configured language code of each of the given users.
	 *
	 * Users who have not explicitly chosen a language have an empty string in their `language` user
	 * parameter, not a missing key — see Setup::getLanguageOptions(). Such users, and users whose
	 * parameters cannot be parsed, are reported with an empty language code; it is up to the caller to
	 * fall back to a sensible default.
	 *
	 * @param   int[]  $userIds  The IDs of the users to look up
	 *
	 * @return  array<int, string>  Language code, keyed by user ID. May be an empty string.
	 * @since   1.4.0
	 */
	private function getRecipientLanguages(array $userIds): array
	{
		$userIds = array_values(array_unique(array_map('intval', $userIds)));

		if (empty($userIds))
		{
			return [];
		}

		$db    = Factory::getContainer()->db;
		$query = $db->getQuery(true)
			->select([$db->quoteName('id'), $db->quoteName('parameters')])
			->from($db->quoteName('#__users'))
			->where($db->quoteName('id') . ' IN(' . implode(',', $userIds) . ')');

		$ret = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			try
			{
				$params = json_decode($row->parameters ?? '{}', flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException)
			{
				$params = null;
			}

			$ret[(int) $row->id] = (string) ($params?->language ?? '');
		}

		return $ret;
	}

	/**
	 * Get an isolated Language object for the given language code.
	 *
	 * We deliberately do NOT use $container->language->loadLanguage(): that would mutate the ambient
	 * language for the remainder of the request, corrupting everything rendered after us — not least the
	 * email whose recipients we are mirroring, which is sent immediately after we are called. AWF's
	 * languageFactory hands us a brand-new, independent instance instead.
	 *
	 * English is loaded first, and the target language on top of it, so that a key missing from the
	 * target language falls back to English instead of the application's default language.
	 *
	 * @param   string  $langCode  The language code to load
	 *
	 * @return  Language
	 * @since   1.4.0
	 */
	private function getLanguageObject(string $langCode): Language
	{
		/** @var Language $language */
		$language = Factory::getContainer()->languageFactory('en-GB');

		if ($langCode !== 'en-GB')
		{
			$language->loadLanguage($langCode);
		}

		return $language;
	}

	/**
	 * Compose the Web Push notification payload in a specific language.
	 *
	 * @param   Language  $lang       The language to compose the payload in
	 * @param   string    $template   The email template this notification mirrors
	 * @param   array     $emailVars  The email variables; used to substitute the site name
	 * @param   string    $clickUrl   The URL to open when the notification is clicked
	 * @param   int|null  $siteId     The site ID this notification refers to
	 *
	 * @return  string  The JSON-encoded payload
	 * @since   1.4.0
	 */
	private function getWebPushPayload(
		Language $lang, string $template, array $emailVars, string $clickUrl, ?int $siteId
	): string
	{
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
		if (isset($emailVars['SITE_NAME']))
		{
			$body = str_replace('[SITE_NAME]', $emailVars['SITE_NAME'], $body);
		}

		if (isset($emailVars['SITENAME']))
		{
			$body = str_replace('[SITENAME]', $emailVars['SITENAME'], $body);
		}

		return json_encode([
			'title' => $title,
			'body'  => $body,
			'icon'  => Uri::base() . 'media/images/logo-color.svg',
			'tag'   => $template . ($siteId ? '-' . $siteId : ''),
			'url'   => $clickUrl,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
