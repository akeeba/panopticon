<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Captcha;

defined('AKEEBA') || die;

use Awf\Container\Container;

class CaptchaFactory
{
	/**
	 * Create a CAPTCHA provider instance based on the provider name.
	 *
	 * @param   string     $provider   The provider name (e.g. 'altcha', 'recaptcha_invisible', 'hcaptcha', 'none')
	 * @param   Container  $container  The application container
	 *
	 * @return  CaptchaInterface|null  The CAPTCHA provider instance, or null for 'none'
	 */
	public static function make(string $provider, Container $container): ?CaptchaInterface
	{
		return match ($provider)
		{
			'altcha'               => new AltchaCaptcha($container),
			'recaptcha_invisible'  => new RecaptchaInvisibleCaptcha($container),
			'hcaptcha'             => new HCaptchaCaptcha($container),
			default                => null,
		};
	}
}
