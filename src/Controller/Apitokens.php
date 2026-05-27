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
use Akeeba\Panopticon\Model\AuditLog;
use Awf\Date\Date;
use Awf\Mvc\DataController;
use Awf\Utils\Ip;

/**
 * Controller for managing API Tokens.
 *
 * Conventions-compliant DataController:
 * - browse / add / edit / save / apply / cancel / publish / unpublish / remove from AWF.
 * - audit-log entries emitted via the on{Before,After}{Task} hooks.
 *
 * @since  1.4.0
 */
class Apitokens extends DataController
{
	use ACLTrait;

	/**
	 * IDs of tokens about to be deleted, captured before {@see DataController::remove()}
	 * actually deletes them so we can audit-log the owner/id.
	 *
	 * @var  array<int, array{owner: int}>
	 */
	private array $pendingDeletes = [];

	public function execute($task)
	{
		$this->aclCheck($task);

		// Gate: users whose effective token limit is 0 may only browse and delete.
		// Super users are never subject to quota restrictions.
		$limitedTasks = ['add', 'edit', 'save', 'apply', 'publish', 'unpublish'];

		if (in_array($task, $limitedTasks, true))
		{
			$user = $this->getContainer()->userManager->getUser();

			if (!$user->getPrivilege('panopticon.super'))
			{
				/** @var Apitoken $model */
				$model = $this->getModel();

				if ($model->getEffectiveLimitForUser((int) $user->getId()) === 0)
				{
					throw new \RuntimeException(
						$this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
						403
					);
				}
			}
		}

		return parent::execute($task);
	}

	/**
	 * Normalise the data being saved.
	 *
	 * @param   array|object|null  $data
	 *
	 * @return  void
	 */
	protected function onBeforeApplySave(array|object|null &$data)
	{
		$data = (array) $data;

		$user      = $this->getContainer()->userManager->getUser();
		$isSuper   = (bool) $user->getPrivilege('panopticon.super');
		$isNew     = empty($data['id']);

		/** @var Apitoken $model */
		$model = $this->getModel();

		// Resolve the target user id (non-supers can only manage their own tokens).
		$targetUserId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

		if ($targetUserId <= 0 || !$isSuper)
		{
			$targetUserId = (int) $user->getId();
		}

		// Normalise expires_at: empty => NULL; otherwise re-format through Date.
		$expiresRaw = isset($data['expires_at']) ? trim((string) $data['expires_at']) : '';

		if ($expiresRaw === '' || $expiresRaw === '0000-00-00 00:00:00')
		{
			$data['expires_at'] = null;
		}
		else
		{
			try
			{
				$data['expires_at'] = $this->getContainer()->dateFactory($expiresRaw)->toSql();
			}
			catch (\Throwable)
			{
				$data['expires_at'] = null;
			}
		}

		// Enabled defaults to 1 for new rows.
		if ($isNew)
		{
			$data['enabled']    = isset($data['enabled']) ? (int) $data['enabled'] : 1;
			$data['user_id']    = $targetUserId;
			$data['seed']       = Apitoken::generateSeed();
			$data['created_by'] = (int) $user->getId();
			$data['created_on'] = $this->getContainer()->dateFactory()->toSql();

			// Cap check: compare against the user's effective (configurable) limit.
			$existing       = $model->countEnabledForUser($targetUserId);
			$effectiveLimit = $model->getEffectiveLimitForUser($targetUserId);

			if ($existing >= $effectiveLimit && (int) ($data['enabled'] ?? 0) === 1)
			{
				throw new \RuntimeException(
					$this->getContainer()->language->text('PANOPTICON_APITOKENS_ERR_LIMIT_EXCEEDED')
				);
			}
		}
		else
		{
			$data['modified_by'] = (int) $user->getId();
			$data['modified_on'] = $this->getContainer()->dateFactory()->toSql();

			// Ownership check: the existing row must be loaded.
			if (!$model->getId())
			{
				$this->getIDsFromRequest($model, true);
			}

			if ((int) $model->user_id !== (int) $user->getId() && !$isSuper)
			{
				throw new \RuntimeException(
					$this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
					403
				);
			}

			// Non-supers cannot change ownership.
			if (!$isSuper)
			{
				unset($data['user_id']);
			}

			// Never let seed/created_* be overwritten via the form.
			unset($data['seed'], $data['created_on'], $data['created_by']);
		}

		// Description: empty string => NULL.
		if (isset($data['description']))
		{
			$data['description'] = trim((string) $data['description']);

			if ($data['description'] === '')
			{
				$data['description'] = null;
			}
		}
	}

	protected function onAfterApplySave(array|object|null &$data): void
	{
		/** @var Apitoken $model */
		$model = $this->getModel();
		$user  = $this->getContainer()->userManager->getUser();
		$ip    = $this->getClientIpBinary();

		$wasNew = empty($data['id']);

		AuditLog::record(
			$wasNew ? 'apitoken.create' : 'apitoken.update',
			(int) $user->getId(),
			$ip,
			'apitoken',
			(int) $model->getId(),
			[
				'target_user_id' => (int) $model->user_id,
				'has_expiry'     => !empty($model->expires_at),
			]
		);
	}

	/**
	 * Called by AWF Controller::execute() after publish() runs. Audit-log each affected row.
	 */
	protected function onAfterPublish(): bool
	{
		$this->auditMassToggle(true);

		return true;
	}

	/**
	 * Called by AWF Controller::execute() after unpublish() runs.
	 */
	protected function onAfterUnpublish(): bool
	{
		$this->auditMassToggle(false);

		return true;
	}

	/**
	 * Called BEFORE remove() so we can capture target ids/owners (they vanish after delete).
	 */
	protected function onBeforeRemove(): bool
	{
		$model = $this->getModel();
		$ids   = $this->getIDsFromRequest($model, false);

		$this->pendingDeletes = [];

		foreach ($ids as $id)
		{
			try
			{
				$tmp = $this->getContainer()->mvcFactory->makeTempModel('Apitoken');
				$tmp->findOrFail((int) $id);

				$this->pendingDeletes[(int) $id] = ['owner' => (int) $tmp->user_id];
			}
			catch (\Throwable)
			{
				// Skip rows that no longer exist.
			}
		}

		return true;
	}

	protected function onAfterRemove(): bool
	{
		$user = $this->getContainer()->userManager->getUser();
		$ip   = $this->getClientIpBinary();

		foreach ($this->pendingDeletes as $id => $info)
		{
			AuditLog::record(
				'apitoken.delete',
				(int) $user->getId(),
				$ip,
				'apitoken',
				$id,
				['target_user_id' => (int) $info['owner']]
			);
		}

		$this->pendingDeletes = [];

		return true;
	}

	/**
	 * Audit-log one entry per row affected by mass publish/unpublish.
	 *
	 * @param   bool  $enabled  The new enabled state.
	 *
	 * @return  void
	 */
	private function auditMassToggle(bool $enabled): void
	{
		$model = $this->getModel();
		$ids   = $this->getIDsFromRequest($model, false);

		if (empty($ids))
		{
			return;
		}

		$user = $this->getContainer()->userManager->getUser();
		$ip   = $this->getClientIpBinary();

		foreach ($ids as $id)
		{
			AuditLog::record(
				'apitoken.toggle',
				(int) $user->getId(),
				$ip,
				'apitoken',
				(int) $id,
				['enabled' => $enabled]
			);
		}
	}

	/**
	 * Get the current client IP packed as a binary string for audit logging.
	 *
	 * @return  string|null
	 * @since   1.4.0
	 */
	private function getClientIpBinary(): ?string
	{
		try
		{
			$ip = Ip::getUserIP();

			if (empty($ip))
			{
				return null;
			}

			$packed = @inet_pton($ip);

			return $packed === false ? null : $packed;
		}
		catch (\Throwable)
		{
			return null;
		}
	}
}
