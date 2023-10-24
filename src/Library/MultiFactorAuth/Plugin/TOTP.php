<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth\Plugin;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Library\View\FakeView;
use Akeeba\Panopticon\Model\Mfa;
use Awf\Event\Observer;
use Awf\Input\Input;
use Awf\Text\Text;
use Awf\Uri\Uri;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use ParagonIE\ConstantTime\Base32;
use stdClass;

/**
 * RFC 6238 TOTP for Multi-factor Authentication in Akeeba Panopticon
 *
 * @since  1.0.2
 * @see    https://datatracker.ietf.org/doc/html/rfc6238
 */
class TOTP extends Observer
{
	private const METHOD_NAME = 'totp';

	private const HELP_URL = 'https://github.com/akeeba/panopticon/wiki/MFA-TOTP';

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public function onMfaGetMethod(): array
	{
		return [
			'name'               => self::METHOD_NAME,
			'display'            => Text::_('PANOPTICON_MFA_TOTP_LBL_DISPLAYEDAS'),
			'shortinfo'          => Text::_('PANOPTICON_MFA_TOTP_LBL_SHORTINFO'),
			'image'              => 'media/mfa/images/totp.svg',
			'allowMultiple'      => false,
			'allowEntryBatching' => false,
			'help_url'           => self::HELP_URL,
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
		$options   = $this->decodeRecordOptions($record);
		$container = Factory::getContainer();

		if (empty($options->totp_secret ?? ''))
		{
			$options->totp_secret = $container->segment->get('totp.secret', null);
			$options->totp_secret ??= Base32::encodeUpper(random_bytes(30));

			$container->segment->set('totp.secret', $options->totp_secret);
		}

		$user = $container->userManager->getUser();
		$totp = \OTPHP\TOTP::createFromSecret($options->totp_secret);
		$totp->setLabel($user->getUsername() . '@' . Uri::getInstance()->getHost());

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
			'default_title'  => Text::_('PANOPTICON_MFA_TOTP_LBL_DEFAULTTITLE'),
			'pre_message'    => $this->renderTemplate(
				'Totpmfa/setup',
				[
					'secret' => $options->totp_secret,
					'uri'    => $totp->getProvisioningUri(),
					'svg'    => (new QRCode(
						new QROptions(
							[
								'version'            => Version::AUTO,
								'outputType'         => QROutputInterface::MARKUP_SVG,
								'outputInterface'    => QRMarkupSVG::class,
								'eol'                => "\n",
								'imageBase64'        => false,
								'eccLevel'           => EccLevel::L,
								'addQuietzone'       => true,
								'drawLightModules'   => false,
								'connectPaths'       => true,
								'keepAsSquare'       => [
									QRMatrix::M_FINDER_DARK,
									QRMatrix::M_FINDER_DOT,
									QRMatrix::M_ALIGNMENT_DARK,
								],
								'excludeFromConnect' => [
								],
								'svgDefs'            => <<<SVG
<style><![CDATA[.dark{fill: var(--bs-body-color, fuchsia);}}]]></style>
SVG
								,
							]
						)
					))
						->render($totp->getProvisioningUri()),
				]
			),
			'hidden_data'    => [
				'secret' => $options->totp_secret,
			],
			'field_type'     => 'input',
			'input_type'     => 'number',
			'autocomplete'   => 'one-time-code',
			'input_value'    => '',
			'placeholder'    => Text::_('PANOPTICON_MFA_TOTP_LBL_PLACEHOLDER'),
			'label'          => Text::_('PANOPTICON_MFA_TOTP_LBL_CODE'),
			'html'           => '',
			'show_submit'    => true,
			'submit_onclick' => '',
			'post_message'   => '',
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

		// Load the options from the record (if any) and merge with submitted form data
		$options   = $this->decodeRecordOptions($record);
		$container = Factory::getContainer();
		$secret    = $input->get(
			'secret',
			$container->segment->get('totp.secret', $options->totp_secret),
			'string'
		);

		try
		{
			$totp = \OTPHP\TOTP::createFromSecret($secret);
		}
		catch (\Throwable $e)
		{
			$totp = \OTPHP\TOTP::generate();
		}

		// Make sure the code is valid
		$code = $input->get('code', '000000');
		$totp->verify($code, null, 15);

		$container->segment->set('totp.secret', null);

		// Return the configuration to be serialized
		return [
			'totp_secret' => $secret,
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
	 * @since   1.0.0
	 */
	public function onMfaCaptive(Mfa $record): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		return [
			'field_type'   => 'input',
			'input_type'   => 'number',
			'autocomplete' => 'one-time-code',
			'placeholder'  => Text::_('PANOPTICON_MFA_TOTP_LBL_PLACEHOLDER'),
			'label'        => Text::_('PANOPTICON_MFA_TOTP_LBL_CODE'),
			'help_url'     => self::HELP_URL,
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
		// TODO Implement me

		// Make sure we are actually meant to handle this method
		if ($record->method != self::METHOD_NAME)
		{
			return false;
		}

		// Load the options from the record (if any)
		$options = $this->decodeRecordOptions($record);

		try
		{
			$totp = \OTPHP\TOTP::createFromSecret($options->totp_secret);
		}
		catch (\Throwable $e)
		{
			$totp = \OTPHP\TOTP::generate();
		}

		// Double-check the TFA method is for the correct user
		if ($user->getId() != $record->user_id)
		{
			return false;
		}

		// Check the TFA code for validity
		return $totp->verify($code, null, 15);
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
			'totp_secret' => '',
		];

		$options = array_merge($options, $record->getOptions() ?: []);

		return (object) $options;
	}

	private function renderTemplate(string $template, array $forceParams = []): string
	{
		[$view,] = explode('/', $template, 2);

		$fakeView = new FakeView(
			Factory::getContainer(), [
				'name' => ucfirst($view),
			]
		);

		return $fakeView->loadAnyTemplate($template, $forceParams);
	}
}