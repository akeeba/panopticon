<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;
use Awf\Uri\Uri;
use Awf\Utils\ArrayHelper;

class Sysconfig extends Controller
{
	use ACLTrait;

	private const CHECKBOX_KEYS = [
		'debug', 'behind_load_balancer', 'stats_collection', 'proxy_enabled', 'phpwarnings', 'log_rotate_compress',
		'dbencryption', 'dbsslverifyservercert', 'dbbackup_auto', 'dbbackup_compress', 'mail_online',
		'mail_inline_images', 'smtpauth',  'login_failure_enable', 'login_lockout_extend', 'avatars', 'password_hibp',
		'session_encrypt', 'session_use_default_path', 'mfa_superuser', 'mfa_admin', 'passkey_login',
		'passkey_login_no_mfa', 'passkey_login_force_superuser', 'passkey_login_force_admin',
		'pwreset', 'pwreset_mfa', 'pwreset_passkeys', 'pwreset_superuser', 'pwreset_admin',
	];

	public function execute($task)
	{
		$this->aclCheck($task);

		// Special case: I am containerised, and PANOPTICON_USING_ENV is 1
		if (defined('APATH_IN_DOCKER') && constant('APATH_IN_DOCKER') && ($_ENV['PANOPTICON_USING_ENV'] ?? 0))
		{
			$this->setRedirect(
				$this->getContainer()->router->route('index.php'),
				$this->getLanguage()->text('PANOPTICON_SYSCONFIG_ERR_USING_DOTENV'),
				'error'
			);

			return true;
		}

		if (BootstrapUtilities::hasConfiguration(true))
		{
			$this->setRedirect(
				$this->getContainer()->router->route('index.php'),
				$this->getLanguage()->text('PANOPTICON_SYSCONFIG_ERR_USING_DOTENV'),
				'error'
			);

			return true;
		}

		return parent::execute($task);
	}

	public function testemail()
	{
		$this->csrfProtection();

		try
		{
			$user   = $this->getContainer()->userManager->getUser();
			$mailer = $this->getContainer()->mailer;

			$mailer->addRecipient($user->getEmail(), $user->getName());
			$mailer->setSubject($this->getLanguage()->text('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_SUBJECT'));
			$mailer->setBody($this->getLanguage()->sprintf('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_BODY', Uri::base()));

			$sent = $mailer->send();

			if (!$sent)
			{
				$error = $mailer->ErrorInfo;

				if (!$this->getContainer()->appConfig->get('mail_online'))
				{
					$error = $error ?: $this->getLanguage()->text('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_IS_DISABLED');
				}
			}
		}
		catch (\Throwable $e)
		{
			$sent  = false;
			$error = $e->getMessage();
		}

		$this->setRedirect(
			$this->getContainer()->router->route('index.php?view=sysconfig'),
			$sent
				? $this->getLanguage()->text('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_SENT')
				: $this->getLanguage()->sprintf('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_NOT_SENT', $error),
			$sent ? 'success' : 'error'
		);
	}

	public function save(): void
	{
		$this->csrfProtection();

		$urlRedirect = $this->input->get('urlredirect', null, 'raw');
		$data        = $this->input->get('options', [], 'none');

		// Handle checkbox keys
		array_walk(
			$data,
			function (&$value, string $key) {
				if (in_array($key, self::CHECKBOX_KEYS))
				{
					$value = in_array(strtolower($value), ['on', 'checked', 1, 'true']);
				}
			}
		);

		foreach (self::CHECKBOX_KEYS as $k)
		{
			$data[$k] ??= false;
		}

		// Apply the configuration to the appConfig object
		$config = $this->container->appConfig;

		foreach ($data as $k => $v)
		{
			$config->set($k, $v);
		}

		$config->set('fs', null);

		// Handle the fields with lists of user groups
		$this->handleGroupsListField('mfa_force_groups');
		$this->handleGroupsListField('passkey_login_force_groups');
		$this->handleGroupsListField('pwreset_groups');

		// Save the appConfig to disk
		$this->container->appConfig->saveConfiguration();

		// Invalidate OPcache for our config.php file, if supported
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($this->container->appConfig->getDefaultPath(), true);
		}

		// Save the extension update preferences
		$data = $this->input->get('extupdates', [], 'email');
		$data = is_array($data) ? $data : [];

		$this->getModel()->saveExtensionPreferences($data);

		$url = $urlRedirect ? base64_decode($urlRedirect) : $this->container->router->route('index.php');

		$this->setRedirect($url, $this->getLanguage()->text('PANOPTICON_SYSCONFIG_MSG_SAVED'));
	}

	public function apply()
	{
		$this->save();

		$url = $this->container->router->route('index.php?view=sysconfig');

		$this->setRedirect($url, $this->getLanguage()->text('PANOPTICON_SYSCONFIG_MSG_SAVED'));
	}

	public function cancel()
	{
		$url = $this->container->router->route('index.php?view=main');

		$this->setRedirect($url);
	}

	private function handleGroupsListField(string $configKey): void
	{
		$groups = $this->input->get($configKey, [], 'array') ?: [];
		$groups = is_string($groups)
			? array_filter(ArrayHelper::toInteger(explode(',', $groups)))
			: $groups;
		$groups = is_array($groups) ? $groups : [$groups];

		$this->container->appConfig->set($configKey, $groups);
	}
}