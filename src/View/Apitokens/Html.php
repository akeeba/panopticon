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

	public function onBeforeBrowse(): bool
	{
		$result = $this->onBeforeBrowseCrud();

		$this->addButtons(['publish', 'unpublish']);

		$this->populateApiUrls();

		return $result;
	}

	protected function onBeforeAdd()
	{
		$result = $this->onBeforeAddCrud();

		$this->populateApiUrls();
		$this->tokenValue = '';

		return $result;
	}

	protected function onBeforeEdit()
	{
		$result = $this->onBeforeEditCrud();

		$this->populateApiUrls();

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
}
