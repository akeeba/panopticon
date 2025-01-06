<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth\Plugin;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Mfa;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Event\Observable;
use Awf\Event\Observer;
use Awf\Input\Input;
use Awf\Text\Language;
use Awf\Text\LanguageAwareInterface;
use Awf\Text\LanguageAwareTrait;
use Awf\Text\Text;
use RuntimeException;
use stdClass;

class FixedCodeDemo
	extends Observer
	implements ContainerAwareInterface, LanguageAwareInterface
{
	use ContainerAwareTrait;
	use LanguageAwareTrait;

	private const METHOD_NAME = 'fixed';

	private const HELP_URL = 'https://github.com/akeeba/panopticon/wiki/MFA-Fixed-Code';

	public function __construct(Observable &$subject, ?Container $container = null, ?Language $language = null)
	{
		parent::__construct($subject);

		$this->setContainer($container ?? Factory::getContainer());
		$this->setLanguage($language ?? $this->getContainer()->language);
	}

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 */
	public function onMfaGetMethod(): array
	{
		return [
			// Internal code of this TFA method
			'name'          => self::METHOD_NAME,
			// User-facing name for this TFA method
			'display'       => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'     => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'         => 'media/mfa/images/fixed.svg',
			// Are we allowed to disable it?
			'canDisable'    => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple' => false,
			// URL for help content
			'help_url'      => self::HELP_URL,
		];
	}

	/**
	 * Returns the information used to render the captive MFA page.
	 *
	 * This is the page which appears right after you log in and asks you to validate your login with MFA.
	 *
	 * @param   Mfa  $record  The #__mfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onMfaCaptive(Mfa $record): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		return [
			// Custom HTML to display above the TFA form
			'pre_message'  => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_PREMESSAGE'),
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'   => 'input',
			// The type attribute for the HTML input box. Typically, "text" or "password". Use any HTML5 input type.
			'input_type'   => 'password',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'  => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'        => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'         => '',
			// Custom HTML to display below the TFA form
			'post_message' => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_POSTMESSAGE'),
			// URL for help content
			'help_url'     => self::HELP_URL,
		];
	}

	/**
	 * Returns the information used to render the MFA setup page.
	 *
	 * This is the page which allows the user to add or modify a MFA method for their user account. If the record does
	 * not correspond to your plugin, return an empty array.
	 *
	 * @param   Mfa  $record  The #__mfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onMfaGetSetup(Mfa $record): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->decodeRecordOptions($record);

		/**
		 * Return the parameters used to render the GUI.
		 *
		 * Some MFA methods need to display a different interface before and after the setup. For example, when setting
		 * up Google Authenticator or a hardware OTP dongle you need the user to enter a MFA code to verify they are in
		 * possession of a correctly configured device. After the setup is complete you don't want them to see that
		 * field again. In the first state you could use the tabular_data to display the setup values, pre_message to
		 * display the QR code and field_type=input to let the user enter the TFA code. In the second state do the same
		 * BUT set field_type=custom, set html='' and show_submit=false to effectively hide the setup form from the
		 * user.
		 */
		return [
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_DEFAULTTITLE'),
			// Custom HTML to display above the TFA setup form
			'pre_message'    => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_SETUP_PREMESSAGE'),
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => [],
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically, "text" or "password". Use any HTML5 input type.
			'input_type'     => 'password',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => $options->fixed_code,
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
			'show_submit'    => true,
			// onclick handler for the submit button (apply the TFA setup)?
			'submit_onclick' => '',
			// Custom HTML to display below the TFA setup form
			'post_message'   => $this->getLanguage()->text('PANOPTICON_MFA_FIXED_LBL_SETUP_POSTMESSAGE'),
			// URL for help content
			'help_url'       => self::HELP_URL,
		];
	}

	/**
	 * Parse the input from the MFA setup page.
	 *
	 * Return the configuration information to be saved to the database.
	 *
	 * If the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   Mfa    $record  The #__mfa record currently selected by the user.
	 * @param   Input  $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 */
	public function onMfaSaveSetup(Mfa $record, Input $input): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->decodeRecordOptions($record);

		// Merge with the submitted form data
		$code = $input->get('code', $options->fixed_code, 'raw');

		// Make sure the code is not empty
		if (empty($code))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_MFA_FIXED_ERR_EMPTYCODE'));
		}

		// Return the configuration to be serialized
		return [
			'fixed_code' => $code,
		];
	}

	/**
	 * Validates the code submitted by the user in the captive MFA page.
	 *
	 * If the record does not correspond to your plugin return FALSE.
	 *
	 * @param   Mfa          $record  The TFA method's record you're validatng against
	 * @param   User         $user    The user record
	 * @param   string|null  $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onMfaValidate(Mfa $record, User $user, ?string $code): bool
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return false;
		}

		// Load the options from the record (if any)
		$options = $this->decodeRecordOptions($record);

		// Double-check the TFA method is for the correct user
		if ($user->getId() != $record->user_id)
		{
			return false;
		}

		// Check the TFA code for validity
		return hash_equals($options->fixed_code, $code ?? '');
	}

	/**
	 * Decodes the options from a #__mfa record into an options object.
	 *
	 * @param   Mfa  $record
	 *
	 * @return  stdClass
	 */
	private function decodeRecordOptions(Mfa $record): object
	{
		$options = [
			'fixed_code' => '',
		];

		$options = array_merge($options, $record->getOptions() ?: []);

		return (object) $options;
	}
}