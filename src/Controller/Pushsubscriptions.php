<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Pushsubscriptions as PushModel;
use Awf\Mvc\Controller;

/**
 * Controller for managing Web Push subscriptions (AJAX)
 *
 * @since  1.3.0
 */
class Pushsubscriptions extends Controller
{
	use ACLTrait;

	/**
	 * Subscribe a browser to Web Push notifications.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function subscribe(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$endpoint  = $this->input->getString('endpoint', '');
		$keyP256dh = $this->input->getString('key_p256dh', '');
		$keyAuth   = $this->input->getString('key_auth', '');
		$encoding  = $this->input->getString('encoding', 'aesgcm');
		$userAgent = $this->input->getString('user_agent', '');

		if (empty($endpoint) || empty($keyP256dh) || empty($keyAuth))
		{
			$this->sendJsonResponse(false, 'Missing required subscription data');

			return;
		}

		/** @var PushModel $model */
		$model = $this->getModel();

		// Remove any existing subscription with the same endpoint
		$model->removeByEndpoint($endpoint);

		// Insert new subscription
		try
		{
			$model->reset();
			$model->save([
				'user_id'    => $user->getId(),
				'endpoint'   => $endpoint,
				'key_p256dh' => $keyP256dh,
				'key_auth'   => $keyAuth,
				'encoding'   => $encoding,
				'user_agent' => $userAgent ?: null,
			]);

			$this->sendJsonResponse(true);
		}
		catch (\Exception $e)
		{
			$this->sendJsonResponse(false, $e->getMessage());
		}
	}

	/**
	 * Unsubscribe a browser from Web Push notifications.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function unsubscribe(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$endpoint = $this->input->getString('endpoint', '');

		if (empty($endpoint))
		{
			$this->sendJsonResponse(false, 'Missing endpoint');

			return;
		}

		/** @var PushModel $model */
		$model = $this->getModel();
		$model->removeByEndpoint($endpoint);

		$this->sendJsonResponse(true);
	}

	/**
	 * Return the VAPID public key and the user's current subscription count.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function status(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		/** @var PushModel $model */
		$model         = $this->getModel();
		$subscriptions = $model->getSubscriptionsForUser($user->getId());

		$this->sendJsonResponse(true, null, [
			'vapidPublicKey'    => $this->getContainer()->vapidHelper->getPublicKey(),
			'subscriptionCount' => count($subscriptions),
		]);
	}

	/**
	 * Dismiss the WebPush prompt with the user's chosen action.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function dismissPrompt(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$action = $this->input->getString('action', 'remind');

		switch ($action)
		{
			case 'declined':
				$user->getParameters()->set('webpush.prompt_state', 'declined');
				break;

			case 'remind':
			default:
				$user->getParameters()->set('webpush.prompt_state', 'remind');
				$user->getParameters()->set('webpush.prompt_until', time() + 86400);
				break;
		}

		$this->getContainer()->userManager->saveUser($user);

		$this->sendJsonResponse(true);
	}

	/**
	 * Runs before executing any task
	 *
	 * @return  bool
	 * @since   1.3.0
	 */
	protected function onBeforeExecute(): bool
	{
		$this->disableLegacyHashes();

		return true;
	}

	/**
	 * Send a JSON response and terminate.
	 *
	 * @param   bool         $success  Was the operation successful?
	 * @param   string|null  $message  Optional message.
	 * @param   array        $data     Optional extra data.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function sendJsonResponse(bool $success, ?string $message = null, array $data = []): void
	{
		$response = array_merge(['success' => $success], $data);

		if ($message !== null)
		{
			$response['message'] = $message;
		}

		@ob_end_clean();
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$this->getContainer()->application->close();
	}

	/**
	 * Disable the legacy triple hashes in front and behind JSON responses.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function disableLegacyHashes(): void
	{
		$doc = $this->getContainer()->application->getDocument();

		if (!$doc instanceof \Awf\Document\Json)
		{
			return;
		}

		$doc->setUseHashes(false);
	}
}
