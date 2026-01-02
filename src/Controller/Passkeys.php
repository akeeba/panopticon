<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\View\Passkeys\Json;
use Awf\Mvc\Controller;

class Passkeys extends Controller
{
	use ACLTrait;

	/**
	 * Generate and return the public key creation options.
	 *
	 * This is used for the first step of attestation (key registration).
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.2.3
	 */
	public function initCreate(): void
	{
		$this->csrfProtection();

		/** @var Json $view */
		$view           = $this->getView();
		$view->response = [];
		$user           = $this->getContainer()->userManager->getUser();

		// Make sure I have a valid user and the server has either GMP or BCComp support.
		if (
			empty($user)
			|| $user->getId() <= 0
			|| !(function_exists('gmp_intval') || function_exists('bccomp'))
		)
		{
			$this->display();

			return;
		}

		$view->response = $this->getModel()->getCreateOptions(
			$this->input->getBool('resident', false)
		);

		$this->display();
	}

	/**
	 * Validate the attestation response.
	 *
	 * This stores a new passkey.
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.2.3
	 */
	public function create()
	{
		$this->csrfProtection();

		/**
		 * Fundamental sanity check: this callback is only allowed after a Public Key has been created server-side and
		 * the user it was created for matches the current user.
		 *
		 * This is also checked in the validateAuthenticationData() so why check here? In case we have the wrong user
		 * I need to fail early with a Panopticon error page instead of falling through the code and possibly
		 * displaying someone else's Webauthn configuration thus mitigating a major privacy and security risk. So,
		 * please, DO NOT remove this sanity check!
		 */
		$session      = $this->getContainer()->segment;
		$storedUserId = $session->get('passkey.registration_user_id', 0);
		$thatUser     = $this->getContainer()->userManager->getUser($storedUserId ?: null);
		$myUser       = $this->getContainer()->userManager->getUser(null);


		// Try to validate the browser data. If there's an error I won't save anything and pass the message to the GUI.
		try
		{
			$this->getModel()->save($this->input->getRaw('data'));
		}
		catch (\Exception $e)
		{
			$error = $e->getMessage();
		}

		// Unset the session variables used for registering authenticators (security precaution).
		$session->remove('passkey.registration_user_id');
		$session->remove('passkey.publicKeyCredentialCreationOptions');

		// Render the GUI and return it
		$layoutParameters = [
			'user'        => $thatUser,
			'allow_add'   => $thatUser->getId() == $myUser->getId(),
			'credentials' => $this->getModel()->getAuthenticationHelper()->getCredentialsRepository()
				->getAll($thatUser->getId()),
			'showImages'  => true,
			'application' => $this->getContainer()->application,
		];

		if (!empty($error))
		{
			$layoutParameters['error'] = $error;
		}

		echo $this->getView()->loadAnyTemplate('Users/form_passkeys', $layoutParameters);
	}

	/**
	 * Saves a new label for the specified credential.
	 *
	 * @return  void
	 * @since   1.2.3
	 */
	public function saveLabel()
	{
		$this->csrfProtection();

		$credentialId = $this->input->getBase64('credential_id', '');
		$newLabel     = $this->input->getString('new_label', '');

		$this->getView()->response = $this->getModel()->saveLabel($credentialId, $newLabel);

		$this->display();
	}

	/**
	 * Deletes the specified credential.
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.2.3
	 */
	public function delete()
	{
		$this->csrfProtection();

		$credentialId = $this->input->getBase64('credential_id', '');

		$this->getView()->response = $this->getModel()->delete($credentialId);

		$this->display();
	}

	/**
	 * Returns the login challenge.
	 *
	 * This is the first step to logging into the site using a passkey.
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.2.3
	 */
	public function challenge()
	{
		$this->csrfProtection();

		$this->getView()->response = $this->getModel()->challenge(
			$this->input->getBase64('return_url', null)
		);

		$this->display();
	}

	/**
	 * Validates the assertion response.
	 *
	 * This is the second step to logging into the site using a passkey.
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.2.3
	 */
	public function login()
	{
		$this->csrfProtection();

		$this->getModel()->login($this->input->get('data', null, 'raw'));
	}

	/**
	 * Runs before executing any task
	 *
	 * @return  bool
	 * @since   1.2.3
	 */
	protected function onBeforeExecute(): bool
	{
		$this->disableLegacyHashes();

		return true;
	}

	/**
	 * Disable the legacy triple hashes in front and behind JSON responses.
	 *
	 * AWF will automatically add the `###` marker in front of and behind the JSON-encoded response in AJAX requests
	 * going through a JSON view. We don't want to do that here.
	 *
	 * @return  void
	 * @since   1.2.3
	 */
	private function disableLegacyHashes(): void
	{
		$doc = $this->getContainer()->application->getDocument();

		if (!$doc instanceof \Awf\Document\Json)
		{
			return;
		}

		$doc->setUseHashes(false);
	}
}