<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Captcha;

defined('AKEEBA') || die;

interface CaptchaInterface
{
	/**
	 * Get the machine-readable name of the CAPTCHA provider.
	 *
	 * @return  string
	 */
	public function getName(): string;

	/**
	 * Get the human-readable label of the CAPTCHA provider.
	 *
	 * @return  string
	 */
	public function getLabel(): string;

	/**
	 * Render the CAPTCHA challenge HTML for inclusion in a form.
	 *
	 * @return  string  The HTML to include in the form
	 */
	public function renderChallenge(): string;

	/**
	 * Validate the submitted CAPTCHA response.
	 *
	 * @return  bool  True if the CAPTCHA was solved correctly
	 */
	public function validateResponse(): bool;
}
