<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Apitoken;
use Awf\Date\Date;
use Awf\Mvc\Controller;

/**
 * Controller for managing API Tokens (standalone page + AJAX token CRUD)
 *
 * @since  1.4.0
 */
class Apitokens extends Controller
{
	use ACLTrait;

	/**
	 * Runs before executing any task.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	protected function onBeforeExecute(): bool
	{
		$this->disableLegacyHashes();

		return true;
	}

	/**
	 * Default task: display the token management page.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	/**
	 * AJAX: Create a new API token.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function create(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$description = $this->input->getString('description', '');
		$targetUserId = $this->input->getInt('user_id', 0);

		// Non-super users can only create tokens for themselves
		if ($targetUserId <= 0 || !$user->getPrivilege('panopticon.super'))
		{
			$targetUserId = $user->getId();
		}

		try
		{
			$seed = Apitoken::generateSeed();

			/** @var Apitoken $model */
			$model = $this->getModel();
			$model->reset();
			$model->save([
				'user_id'     => $targetUserId,
				'description' => $description ?: null,
				'seed'        => $seed,
				'enabled'     => 1,
				'created_by'  => $user->getId(),
				'created_on'  => (new Date())->toSql(),
			]);

			// Compute the token value to return
			$siteSecret = $this->getContainer()->appConfig->get('secret', '');
			$tokenValue = Apitoken::computeToken($seed, $targetUserId, $siteSecret);

			$this->sendJsonResponse(true, null, [
				'id'          => $model->getId(),
				'description' => $description,
				'token'       => $tokenValue,
			]);
		}
		catch (\Exception $e)
		{
			$this->sendJsonResponse(false, $e->getMessage());
		}
	}

	/**
	 * AJAX: Toggle a token's enabled status.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function toggle(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$tokenId = $this->input->getInt('id', 0);

		try
		{
			/** @var Apitoken $model */
			$model = $this->getModel();
			$model->findOrFail($tokenId);

			// Check ownership or super user
			if ($model->user_id != $user->getId() && !$user->getPrivilege('panopticon.super'))
			{
				$this->sendJsonResponse(false, 'Forbidden');

				return;
			}

			$model->save([
				'enabled'     => $model->enabled ? 0 : 1,
				'modified_by' => $user->getId(),
				'modified_on' => (new Date())->toSql(),
			]);

			$this->sendJsonResponse(true, null, [
				'id'      => $model->getId(),
				'enabled' => (bool) $model->enabled,
			]);
		}
		catch (\Exception $e)
		{
			$this->sendJsonResponse(false, $e->getMessage());
		}
	}

	/**
	 * AJAX: Delete a token.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function remove(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$tokenId = $this->input->getInt('id', 0);

		try
		{
			/** @var Apitoken $model */
			$model = $this->getModel();
			$model->findOrFail($tokenId);

			// Check ownership or super user
			if ($model->user_id != $user->getId() && !$user->getPrivilege('panopticon.super'))
			{
				$this->sendJsonResponse(false, 'Forbidden');

				return;
			}

			$model->delete();

			$this->sendJsonResponse(true);
		}
		catch (\Exception $e)
		{
			$this->sendJsonResponse(false, $e->getMessage());
		}
	}

	/**
	 * AJAX: Get a token's computed value.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function getTokenValue(): void
	{
		$this->csrfProtection();

		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getId())
		{
			$this->sendJsonResponse(false, 'Not logged in');

			return;
		}

		$tokenId = $this->input->getInt('id', 0);

		try
		{
			/** @var Apitoken $model */
			$model = $this->getModel();
			$model->findOrFail($tokenId);

			// Check ownership or super user
			if ($model->user_id != $user->getId() && !$user->getPrivilege('panopticon.super'))
			{
				$this->sendJsonResponse(false, 'Forbidden');

				return;
			}

			$siteSecret = $this->getContainer()->appConfig->get('secret', '');
			$tokenValue = Apitoken::computeToken($model->seed, $model->user_id, $siteSecret);

			$this->sendJsonResponse(true, null, [
				'token' => $tokenValue,
			]);
		}
		catch (\Exception $e)
		{
			$this->sendJsonResponse(false, $e->getMessage());
		}
	}

	/**
	 * Send a JSON response and terminate.
	 *
	 * @param   bool         $success  Was the operation successful?
	 * @param   string|null  $message  Optional message.
	 * @param   array        $data     Optional extra data.
	 *
	 * @return  void
	 * @since   1.4.0
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
	 * @since   1.4.0
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
