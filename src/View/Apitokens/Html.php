<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Apitokens;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Apitoken;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Uri\Uri;
use Awf\Utils\Ip;

class Html extends BaseHtmlView
{
	use CrudTasksTrait {
		onBeforeBrowse as onBeforeBrowseCrud;
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	/** @var string The API endpoint base URL (with .htaccess / URL rewriting) */
	public string $apiUrl = '';

	/** @var string The API endpoint base URL (without .htaccess / URL rewriting, fallback) */
	public string $apiUrlFallback = '';

	/** @var string The computed token value, visible on the edit page only */
	public string $tokenValue = '';

	/** @var int The current user's effective API token limit (0 = API access denied). */
	public int $tokenLimit = 0;

	/** @var int The current user's count of enabled, non-expired tokens. */
	public int $tokenCount = 0;

	/** @var bool True when the current user has reached or exceeded their token limit. */
	public bool $isOverQuota = false;

	/** @var bool True when the current user's effective limit is 0 (API access fully denied). */
	public bool $isZeroLimit = false;

	public function onBeforeBrowse(): bool
	{
		$result = $this->onBeforeBrowseCrud();

		$this->addButtons(['publish', 'unpublish']);

		$this->populateApiUrls();
		$this->populateQuota();

		return $result;
	}

	protected function onBeforeAdd()
	{
		$result = $this->onBeforeAddCrud();

		$this->populateApiUrls();
		$this->populateQuota();
		$this->tokenValue = '';

		return $result;
	}

	protected function onBeforeEdit()
	{
		$result = $this->onBeforeEditCrud();

		$this->populateApiUrls();
		$this->populateQuota();

		/** @var Apitoken $model */
		$model = $this->getModel();
		$user  = $this->getContainer()->userManager->getUser();

		$siteSecret       = $this->getContainer()->appConfig->get('secret', '');
		$this->tokenValue = ($model->seed && $model->user_id)
			? Apitoken::computeToken($model->seed, (int) $model->user_id, $siteSecret)
			: '';

		// Audit-log token view (once per page load)
		if ($model->getId())
		{
			$ipPacked = null;

			try
			{
				$ipStr = Ip::getUserIP();

				if (!empty($ipStr))
				{
					$packed   = @inet_pton($ipStr);
					$ipPacked = $packed === false ? null : $packed;
				}
			}
			catch (\Throwable)
			{
				$ipPacked = null;
			}

			AuditLog::record(
				'apitoken.view',
				(int) $user->getId(),
				$ipPacked,
				'apitoken',
				(int) $model->getId()
			);
		}

		return $result;
	}

	private function populateApiUrls(): void
	{
		$base                 = rtrim(Uri::base(), '/');
		$this->apiUrl         = $base . '/api';
		$this->apiUrlFallback = $base . '/index.php/api';
	}

	/**
	 * Populate quota properties for the current (non-super) user.
	 *
	 * Super users are exempt from all quota restrictions so quota display is
	 * suppressed for them (limit shown as PHP_INT_MAX, isOverQuota always false).
	 *
	 * @return  void
	 * @since   1.5.0
	 */
	private function populateQuota(): void
	{
		$user = $this->getContainer()->userManager->getUser();

		if ($user->getPrivilege('panopticon.super'))
		{
			$this->tokenLimit  = PHP_INT_MAX;
			$this->tokenCount  = 0;
			$this->isZeroLimit = false;
			$this->isOverQuota = false;

			return;
		}

		/** @var Apitoken $model */
		$model             = $this->getModel();
		$userId            = (int) $user->getId();
		$this->tokenLimit  = $model->getEffectiveLimitForUser($userId);
		$this->tokenCount  = $model->countEnabledForUser($userId);
		$this->isZeroLimit = ($this->tokenLimit === 0);
		$this->isOverQuota = !$this->isZeroLimit && ($this->tokenCount >= $this->tokenLimit);
	}
}
