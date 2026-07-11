<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Plugin;

use Awf\Registry\Registry;

defined('AKEEBA') || die;

/**
 * Abstract base class for notification-channel plugins (e.g. ntfy.sh, Slack, Discord, ...).
 *
 * onNotificationSend is fired once per call to EmailSendingTrait::enqueueEmail(), i.e. once per
 * outgoing notification, alongside (not instead of) the built-in email and Web Push channels.
 *
 * Concrete plugins are entirely responsible for:
 *  - Formatting their own message content from the raw template key and variables in $data — there
 *    is no shared DB-backed template for third-party channels (mirror WebPushSendingTrait's ad hoc
 *    language-string approach if you want one).
 *  - Storing their own opt-in/subscription state, e.g. a JSON blob in the user's `parameters`
 *    column, or their own DB table which they create/maintain themselves; core's src/schema/mysql.xml
 *    is not extensible by plugins.
 *  - Actually delivering the notification: either synchronously inside onNotificationSend() for
 *    lightweight calls, or by pushing to their own queue (any string queue name works with
 *    QueueFactory::makeQueue(), not just QueueTypeEnum cases) and shipping their own
 *    #[AsTask]-attributed consumer under user_code/Task/ (auto-discovered exactly like core tasks).
 *
 * @since  __DEPLOY_VERSION__
 */
abstract class AbstractNotificationPlugin extends PanopticonPlugin
{
	/** @inheritDoc */
	public function getObservableEvents(): array
	{
		return [
			'onNotificationSend',
		];
	}

	/**
	 * Called for every notification Panopticon wants to send out (in addition to email and Web Push).
	 *
	 * @param   Registry  $data     The same Registry passed to EmailSendingTrait::enqueueEmail(): keys
	 *                              include `template`, `language`, `email_variables`,
	 *                              `email_variables_by_lang`, `permissions`, `recipient_id`, `email_cc`,
	 *                              `email_groups`, `only_email_groups`.
	 * @param   int|null  $siteId   The site this notification refers to, or NULL for system-level.
	 * @param   int[]     $userIds  Pre-resolved recipient user IDs (see RecipientResolver). Plugins
	 *                              should not re-resolve recipients themselves.
	 *
	 * @return  void
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function onNotificationSend(Registry $data, ?int $siteId, array $userIds): void;
}
