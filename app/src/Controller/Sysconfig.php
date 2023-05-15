<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;
use Awf\Text\Text;

class Sysconfig extends Controller
{
	use ACLTrait;

	private const CHECKBOX_KEYS = [
		'debug', 'log_rotate_compress', 'phpwarnings'
	];

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
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
					$value = in_array(strtolower($value), ['on', 'checked', 1, 'true']) ? 1 : 0;
				}
			}
		);

		foreach (self::CHECKBOX_KEYS as $k)
		{
			$data[$k] ??= 0;
		}

		$config = $this->container->appConfig;

		foreach ($data as $k => $v)
		{
			// TODO Validate
			$config->set($k, $v);
		}

		$this->container->appConfig->saveConfiguration();

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($this->container->appConfig->getDefaultPath(), true);
		}

		$url = $urlRedirect ? base64_decode($urlRedirect) : $this->container->router->route('index.php');

		$this->setRedirect($url, Text::_('PANOPTICON_SYSCONFIG_MSG_SAVED'));
	}

	public function apply()
	{
		$this->save();

		$url = $this->container->router->route('index.php?view=sysconfig');

		$this->setRedirect($url, Text::_('PANOPTICON_SYSCONFIG_MSG_SAVED'));
	}

	// TODO Implement testemail()
}