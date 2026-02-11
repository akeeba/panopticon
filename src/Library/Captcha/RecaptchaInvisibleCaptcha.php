<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Captcha;

defined('AKEEBA') || die;

use Awf\Container\Container;
use GuzzleHttp\RequestOptions;

/**
 * Google reCAPTCHA Invisible CAPTCHA implementation.
 *
 * Uses the reCAPTCHA v2 Invisible widget which triggers automatically on form submit.
 * The challenge is verified server-side via Google's siteverify API.
 */
class RecaptchaInvisibleCaptcha implements CaptchaInterface
{
	private string $siteKey;

	private string $secretKey;

	public function __construct(
		private readonly Container $container
	)
	{
		$this->siteKey   = trim((string) $this->container->appConfig->get('captcha_recaptcha_site_key', ''));
		$this->secretKey = trim((string) $this->container->appConfig->get('captcha_recaptcha_secret_key', ''));
	}

	public function getName(): string
	{
		return 'recaptcha_invisible';
	}

	public function getLabel(): string
	{
		return 'reCAPTCHA Invisible (Google)';
	}

	public function renderChallenge(): string
	{
		if (empty($this->siteKey))
		{
			return '';
		}

		$siteKeyAttr = htmlspecialchars($this->siteKey, ENT_QUOTES, 'UTF-8');

		return <<<HTML
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div id="panopticon-recaptcha-invisible"></div>
<script>
    var onRecaptchaSubmit = function(token) {
        document.getElementById("panopticon-recaptcha-invisible").closest("form").submit();
    };
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.getElementById("panopticon-recaptcha-invisible").closest("form");
        if (!form) return;
        form.addEventListener("submit", function(e) {
            if (document.getElementById("g-recaptcha-response") && document.getElementById("g-recaptcha-response").value) return;
            e.preventDefault();
            grecaptcha.execute();
        });
    });
</script>
<div class="g-recaptcha" data-sitekey="{$siteKeyAttr}" data-size="invisible" data-callback="onRecaptchaSubmit"></div>
HTML;
	}

	public function validateResponse(): bool
	{
		if (empty($this->secretKey))
		{
			return false;
		}

		$input    = $this->container->input;
		$response = $input->get('g-recaptcha-response', '', 'raw');

		if (empty($response))
		{
			return false;
		}

		try
		{
			$options              = $this->container->httpFactory->getDefaultRequestOptions();
			$options[RequestOptions::FORM_PARAMS] = [
				'secret'   => $this->secretKey,
				'response' => $response,
			];

			$client   = $this->container->httpFactory->makeClient(cache: false, singleton: false);
			$httpResp = $client->post('https://www.google.com/recaptcha/api/siteverify', $options);

			$body = json_decode((string) $httpResp->getBody(), true);

			return !empty($body['success']);
		}
		catch (\Throwable)
		{
			return false;
		}
	}
}
