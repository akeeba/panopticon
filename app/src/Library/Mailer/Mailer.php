<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Mailer;

defined('AKEEBA') || die;

use Awf\Application\Application;
use Awf\Mailer\Mailer as AWFMailer;
use Awf\Text\Text;

class Mailer extends AWFMailer
{
	public function __construct($container = null)
	{
		if (!is_object($container))
		{
			$container = Application::getInstance()->getContainer();
		}

		parent::__construct();

		$config = $container->appConfig;

		$smtpauth   = !$config->get('smtpauth') ? null : 1;
		$smtpuser   = $config->get('smtpuser');
		$smtppass   = $config->get('smtppass');
		$smtphost   = $config->get('smtphost', 'localhost');
		$smtpsecure = $config->get('smtpsecure', 'none');
		$smtpport   = $config->get('smtpport', 25);
		$mailfrom   = $config->get('mailfrom');
		$fromname   = $config->get('fromname');
		$mailer     = $config->get('mailer');

		$this->SetFrom($mailfrom, $fromname);
		$this->container = $container;

		switch ($mailer)
		{
			case 'smtp':
				$this->useSMTP($smtpauth, $smtphost, $smtpuser, $smtppass, $smtpsecure, $smtpport);
				break;

			case 'sendmail':
				$this->IsSendmail();
				break;

			default:
				$this->IsMail();
				break;
		}
	}

	public function Send()
	{
		$config = $this->container->appConfig;

		if ($config->get('mail_online', false))
		{
			if (($this->Mailer == 'mail') && !function_exists('mail'))
			{
				throw new \RuntimeException(sprintf('%s::Send mail not enabled.', get_class($this)));
			}

			@$result = parent::Send();

			if (!$result)
			{
				throw new \RuntimeException(sprintf('%s::Send failed: "%s".', get_class($this), $this->ErrorInfo));
			}

			return $result;
		}
		else
		{
			$this->container->application->enqueueMessage(Text::_('AWF_MAIL_FUNCTION_OFFLINE'));

			return false;
		}
	}

}