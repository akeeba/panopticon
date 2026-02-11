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
 * hCaptcha Invisible CAPTCHA implementation.
 *
 * Uses hCaptcha's invisible mode which triggers automatically on form submit.
 * The challenge is verified server-side via hCaptcha's siteverify API.
 */
class HCaptchaCaptcha implements CaptchaInterface
{
	private string $siteKey;

	private string $secretKey;

	public function __construct(
		private readonly Container $container
	)
	{
		$this->siteKey   = trim((string) $this->container->appConfig->get('captcha_hcaptcha_site_key', ''));
		$this->secretKey = trim((string) $this->container->appConfig->get('captcha_hcaptcha_secret_key', ''));
	}

	public function getName(): string
	{
		return 'hcaptcha';
	}

	public function getLabel(): string
	{
		return 'hCaptcha';
	}

	public function renderChallenge(): string
	{
		if (empty($this->siteKey))
		{
			return '';
		}

		$siteKeyAttr = htmlspecialchars($this->siteKey, ENT_QUOTES, 'UTF-8');

		return <<<HTML
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<div id="panopticon-hcaptcha-invisible"></div>
<script>
    var onHCaptchaSubmit = function(token) {
        document.getElementById("panopticon-hcaptcha-invisible").closest("form").submit();
    };
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.getElementById("panopticon-hcaptcha-invisible").closest("form");
        if (!form) return;
        form.addEventListener("submit", function(e) {
            if (document.getElementById("h-captcha-response") && document.getElementById("h-captcha-response").value) return;
            e.preventDefault();
            hcaptcha.execute();
        });
    });
</script>
<div class="h-captcha" data-sitekey="{$siteKeyAttr}" data-size="invisible" data-callback="onHCaptchaSubmit"></div>
HTML;
	}

	public function validateResponse(): bool
	{
		if (empty($this->secretKey))
		{
			return false;
		}

		$input    = $this->container->input;
		$response = $input->get('h-captcha-response', '', 'raw');

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
				'sitekey'  => $this->siteKey,
			];

			$client   = $this->container->httpFactory->makeClient(cache: false, singleton: false);
			$httpResp = $client->post('https://api.hcaptcha.com/siteverify', $options);

			$body = json_decode((string) $httpResp->getBody(), true);

			return !empty($body['success']);
		}
		catch (\Throwable)
		{
			return false;
		}
	}
}
