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
		'mail_online', 'mail_smtpauth'
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

		$config = $this->container->appConfig;

		// TODO Handle checkbox keys
		foreach ($data as $k => $v)
		{
			$config->set($k, $v);
		}

		$this->container->appConfig->saveConfiguration();

		$url = $urlRedirect ? base64_decode($urlRedirect) : $this->container->router->route('index.php');

		$this->setRedirect($url, Text::_('SOLO_SYSCONFIG_SAVE'));
	}

	public function apply()
	{
		$this->save();

		$url = $this->container->router->route('index.php?view=sysconfig');

		$this->setRedirect($url, Text::_('SOLO_SYSCONFIG_SAVE'));
	}

	// TODO Implement testemail()
}