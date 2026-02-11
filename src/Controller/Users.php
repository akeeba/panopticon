<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Captcha\CaptchaFactory;
use Akeeba\Panopticon\Library\Passkey\Authentication;
use Akeeba\Panopticon\View\Users\Html;
use Awf\Mvc\DataController;
use Awf\Utils\ArrayHelper;
use RuntimeException;

class Users extends DataController
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	/**
	 * Password reset request
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.3.0
	 */
	public function pwreset(): void
	{
		$username = $this->input->getUsername('username', '');
		$email    = $this->input->get('email', '', 'raw');

		if (empty($username) || empty($email))
		{
			$this->getView()->setLayout('pwreset');
			$this->display();

			return;
		}

		/** @var \Akeeba\Panopticon\Model\Users $model */
		$model = $this->getModel();
		$logger = Factory::getContainer()->loggerFactory->get('login');

		try
		{
			$model->createPasswordResetRequest($username, $email);

			$logger->info(
				sprintf(
					'Created a password reset request for username ‘%s’, email ‘%s’',
					$username,
					$email
				)
			);

			$message = Factory::getContainer()->language->text('PANOPTICON_USERS_LBL_PWRESET_SENT');
			$type    = 'info';
		}
		catch (\Throwable $e)
		{
			$logger->warning(
				sprintf(
					'Could not create a password reset request for username ‘%s’, email ‘%s’. Reason: %s',
					$username,
					$email,
					$e->getMessage()
				)
			);

			$message = $e->getMessage();
			$type    = 'error';
		}

		$this->setRedirect(
			Factory::getContainer()->router->route('index.php'),
			$message,
			$type
		);
	}

	/**
	 * Password reset confirmation
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.3.0
	 */
	public function confirmreset(): void
	{
		$container = Factory::getContainer();
		$router    = $container->router;
		$lang      = $container->language;

		$id       = $this->input->getInt('id', 0);
		$token    = $this->input->getString('token', '');
		$password = $this->input->get('password', '', 'raw');

		// We need a user ID
		if ($id == 0)
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		// We need a valid user which can be password-reset and is in the process of having their password reset.
		$user = $container->userManager->getUser($id);

		if (!$user || !$this->getModel()->canResetPassword($user) || empty($user->getParameters()->get('pwreset.secret', null)))
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		// If no token or password was provided, ask the user for the token.
		if (empty($token) || empty($password))
		{
			/** @var Html $view */
			$view        = $this->getView();
			$view->user  = $user;
			$view->token = $token;

			$view->setLayout('confirmreset');
			$this->display();

			return;
		}

		$logger = Factory::getContainer()->loggerFactory->get('login');

		try
		{
			$logger->debug(
				sprintf(
					'Evaluating password reset for username ‘%s’.',
					$user->getUsername()
				)
			);
			$this->getModel()->passwordReset($user, $token, $password);

			$logger->info(
				sprintf(
					'Successful password reset for username ‘%s’.',
					$user->getUsername()
				)
			);

			$message = $lang->text('PANOPTICON_USERS_LBL_PWRESET_RESET');
			$type    = 'success';
		}
		catch (\Throwable $e)
		{
			$logger->error(
				sprintf(
					'Failed password reset for username ‘%s’. Reason: %s',
					$user->getUsername(),
					$e->getMessage()
				)
			);

			// Advance the failed password reset counter
			$count = ($user->getParameters()->get('pwreset.count', null) ?: 0) + 1;
			$user->getParameters()->get('pwreset.count', $count);
			Factory::getContainer()->userManager->saveUser($user);

			// Redirect with error
			$message = $lang->text('PANOPTICON_USERS_LBL_PWRESET_NOT_RESET');
			$type    = 'error';
		}

		$this->setRedirect($router->route('index.php'), $message, $type);
	}

	/**
	 * User self-registration
	 *
	 * @return  void
	 * @throws  \Exception
	 */
	public function register(): void
	{
		$container = Factory::getContainer();
		$router    = $container->router;
		$lang      = $container->language;
		$appConfig = $container->appConfig;

		$registrationType = $appConfig->get('user_registration', 'disabled');

		// If registration is disabled, redirect with an error
		if (!in_array($registrationType, ['admin', 'self'], true))
		{
			$this->setRedirect(
				$router->route('index.php'),
				$lang->text('PANOPTICON_USERS_ERR_REGISTRATION_DISABLED'),
				'error'
			);

			return;
		}

		// Check for form data (POST request)
		$username  = $this->input->getUsername('username', '');
		$email     = $this->input->get('email', '', 'raw');
		$name      = $this->input->getString('name', '');
		$password  = $this->input->get('password', '', 'raw');
		$password2 = $this->input->get('password2', '', 'raw');

		// If no form data, show the registration form
		if (empty($username) || empty($email))
		{
			$this->getView()->setLayout('register');
			$this->display();

			return;
		}

		$logger = Factory::getContainer()->loggerFactory->get('login');

		try
		{
			// Validate CAPTCHA
			$captchaProvider = $appConfig->get('captcha_provider', 'altcha');
			$captcha         = CaptchaFactory::make($captchaProvider, $container);

			if ($captcha !== null && !$captcha->validateResponse())
			{
				throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_CAPTCHA_FAILED'));
			}

			// Validate passwords match
			if ($password !== $password2)
			{
				throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_PASSWORD_MISMATCH'));
			}

			/** @var \Akeeba\Panopticon\Model\Users $model */
			$model = $this->getModel();
			$user  = $model->createRegistration($username, $email, $password, $name);

			$logger->info(
				sprintf(
					'New user registration: username '%s', email '%s', type '%s'',
					$username,
					$email,
					$registrationType
				)
			);

			$message = ($registrationType === 'admin')
				? $lang->text('PANOPTICON_USERS_LBL_REGISTER_SUCCESS_ADMIN')
				: $lang->text('PANOPTICON_USERS_LBL_REGISTER_SUCCESS_SELF');

			$this->setRedirect(
				$router->route('index.php'),
				$message,
				'success'
			);
		}
		catch (\Throwable $e)
		{
			$logger->warning(
				sprintf(
					'Failed registration attempt: username '%s', email '%s'. Reason: %s',
					$username,
					$email,
					$e->getMessage()
				)
			);

			$this->setRedirect(
				$router->route('index.php?view=users&task=register'),
				$e->getMessage(),
				'error'
			);
		}
	}

	/**
	 * User account activation (self-approval mode)
	 *
	 * @return  void
	 * @throws  \Exception
	 */
	public function activate(): void
	{
		$container = Factory::getContainer();
		$router    = $container->router;
		$lang      = $container->language;
		$appConfig = $container->appConfig;

		$registrationType = $appConfig->get('user_registration', 'disabled');

		// If registration is not self-approval, redirect
		if ($registrationType !== 'self')
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		$id       = $this->input->getInt('id', 0);
		$token    = $this->input->getString('token', '');
		$username = $this->input->getUsername('username', '');
		$password = $this->input->get('password', '', 'raw');

		// We need a user ID
		if ($id == 0)
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		// Load the user
		$user = $container->userManager->getUser($id);

		if (!$user || !$user->getId())
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		// Verify the user has a pending self-registration
		if ($user->getParameters()->get('registration.type') !== 'self' ||
			empty($user->getParameters()->get('registration.secret', '')))
		{
			$this->setRedirect($router->route('index.php'));

			return;
		}

		// If no username/password/token provided, show the activation form
		if (empty($username) || empty($password))
		{
			/** @var Html $view */
			$view        = $this->getView();
			$view->user  = $user;
			$view->token = $token;

			$view->setLayout('activate');
			$this->display();

			return;
		}

		$logger = Factory::getContainer()->loggerFactory->get('login');

		/** @var \Akeeba\Panopticon\Model\Users $model */
		$model = $this->getModel();

		// Check activation time expiry
		$maxDays     = (int) $appConfig->get('user_registration_activation_days', 7);
		$createdTime = (int) $user->getParameters()->get('registration.created', 0);
		$maxTime     = $createdTime + ($maxDays * 86400);

		if (time() > $maxTime)
		{
			$logger->info(sprintf('Activation expired for user '%s' (time limit)', $user->getUsername()));

			$model->sendExpiredAndDelete($user);

			$this->setRedirect(
				$router->route('index.php'),
				$lang->text('PANOPTICON_USERS_LBL_ACTIVATE_EXPIRED'),
				'error'
			);

			return;
		}

		// Check activation tries
		$maxTries     = (int) $appConfig->get('user_registration_activation_tries', 3);
		$currentTries = (int) $user->getParameters()->get('registration.activation_tries', 0);

		if ($currentTries >= $maxTries)
		{
			$logger->info(sprintf('Activation expired for user '%s' (too many tries)', $user->getUsername()));

			$model->sendExpiredAndDelete($user);

			$this->setRedirect(
				$router->route('index.php'),
				$lang->text('PANOPTICON_USERS_LBL_ACTIVATE_EXPIRED'),
				'error'
			);

			return;
		}

		// Try to validate the token
		if (!$model->validateActivationToken($user, $username, $password, $token))
		{
			// Increment tries
			$user->getParameters()->set('registration.activation_tries', $currentTries + 1);
			$container->userManager->saveUser($user);

			$logger->warning(sprintf('Failed activation attempt for user '%s'', $user->getUsername()));

			$this->setRedirect(
				$router->route(sprintf('index.php?view=users&task=activate&id=%d', $id)),
				$lang->text('PANOPTICON_USERS_LBL_ACTIVATE_FAILED'),
				'error'
			);

			return;
		}

		// Activation successful
		$model->activateRegistration($user);

		$logger->info(sprintf('User '%s' successfully activated their account', $user->getUsername()));

		$this->setRedirect(
			$router->route('index.php'),
			$lang->text('PANOPTICON_USERS_LBL_ACTIVATE_SUCCESS'),
			'success'
		);
	}

	protected function onBeforeEdit()
	{
		$this->getView()->collapseForMFA     = $this->input->get('collapseForMFA', 0);
		$this->getView()->collapseForPasskey = $this->input->get('collapseForPasskey', 0);

		return $this->isThisMyOwnUserOrAmISuper();
	}

	protected function onBeforeRead()
	{
		$model = $this->getModel();

		if (!$model->getId())
		{
			$ids = $this->getIDsFromRequest($model, true);

			// No ID in the request? Force it to the current user, to make things simpler.
			if (empty($ids))
			{
				$this->input->set('id', $this->container->userManager->getUser()?->getId());
				$ids = [$this->input->get('id', null)];
				$model->find($ids[0]);
			}

			if ($model->getId() != reset($ids))
			{
				return false;
			}
		}

		return $this->isThisMyOwnUserOrAmISuper();
	}

	protected function onBeforeSave()
	{
		$this->overrideRedirectForNonSuper();
		$this->overrideRedirectForForcedMFA();

		return $this->isThisMyOwnUserOrAmISuper();
	}

	protected function onBeforeApply()
	{
		$this->overrideRedirectForNonSuper();
		$this->overrideRedirectForForcedMFA();

		return $this->isThisMyOwnUserOrAmISuper();
	}

	protected function onAfterApply()
	{
		$collapseForMFA     = $this->input->get('collapseForMFA', 0);
		$collapseForPasskey = $this->input->get('collapseForPasskey', 0);

		if (!$collapseForMFA && !$collapseForPasskey)
		{
			return true;
		}

		$returnUrl = $this->input->getBase64('returnUrl');

		$this->setRedirect(base64_decode($returnUrl));

		return true;
	}

	protected function onBeforeCancel()
	{
		$this->overrideRedirectForNonSuper();
		$this->overrideRedirectForForcedMFA();

		return $this->isThisMyOwnUserOrAmISuper();
	}

	protected function applySave()
	{
		$id = $this->input->getInt('id', 0);

		$this->layout ??= 'form';

		// Get the user objects. Myself doing the saving, and the user being saved
		$myself        = $this->container->userManager->getUser();
		$savedUser     = $this->container->userManager->getUser($id);
		$isNewUser     = empty($id) || ($savedUser->getId() != $id);
		$editingMyself = $savedUser->getId() == $myself->getId();

		// Track blocked state for registration approval flow
		$wasBlocked = !$isNewUser && $savedUser->getParameters()->get('block', false);

		// Get the applicable data
		$data = [
			'id'          => $id,
			'username'    => trim((string) $this->input->post->getUsername('username', '')),
			'name'        => trim((string) $this->input->post->getString('name', '')),
			'email'       => trim((string) $this->input->post->get('email', '', 'raw')),
			'password'    => $this->input->post->get('password', '', 'raw'),
			'password2'   => $this->input->post->get('password2', '', 'raw'),
			'groups'      => array_filter(ArrayHelper::toInteger($this->input->post->get('groups', [], 'raw'))),
			'permissions' => array_keys($this->input->post->get('permissions', [], 'raw')),
		];

		$params = [
			'language'                  => $this->input->post->getCmd('language', ''),
			'main_layout'               => $this->input->post->getCmd('main_layout', 'default'),
			'passkey_login_no_password' => $this->input->post->getBool('passkey_login_no_password', false),
		];

		// Only allow setting passkey_login_no_password if passkeys are enabled, and the user is allowed to decide.
		$canDecide = $this->getContainer()->mvcFactory->makeTempModel('Passkeys')->isEnabled()
		             && $this->getContainer()->appConfig->get('passkey_login_no_password', 'user') === 'user';

		if (!$canDecide)
		{
			unset($params['passkey_login_no_password']);
		}

		// Apply groups if the editing user is a Super User
		if (!$myself->getPrivilege('panopticon.super'))
		{
			$data['username']    = $savedUser->getUsername();
			$data['groups']      = $savedUser->getParameters()->get('usergroups', []);
			$data['permissions'] = [];
		}

		try
		{
			if (method_exists($this, 'onBeforeApplySave'))
			{
				$this->onBeforeApplySave($data);
			}

			// If I am a superuser I can change the username.
			if ($myself->getPrivilege('panopticon.super'))
			{
				// Do I even have a username?
				$username = $data['username'];

				if (empty($username))
				{
					throw new RuntimeException(
						$this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_EMPTYUSERNAME'), 403
					);
				}

				// Is there another user by the same username?
				if ($savedUser->getUsername() !== $username
				    && $this->container->userManager->getUserByUsername(
						$username
					) !== null)
				{
					throw new RuntimeException(
						$this->getLanguage()->sprintf('PANOPTICON_USERS_ERR_USERNAME_EXISTS', htmlentities($username)),
						403
					);
				}

				$savedUser->setUsername($username);
			}

			// Change or set the password, if necessary
			$password  = $data['password'];
			$password2 = $data['password2'];

			if ($isNewUser || !empty($password) || !empty($password2))
			{
				$emptyPassword  = empty($password);
				$emptyPassword2 = empty($password2);
				$passwordsMatch = $password === $password2;

				if ($isNewUser && ($emptyPassword || $emptyPassword2))
				{
					throw new RuntimeException($this->getLanguage()->text('PANOPTICON_USERS_ERR_NEEDS_PASSWORD'), 403);
				}
				elseif (!$passwordsMatch)
				{
					throw new RuntimeException(
						$this->getLanguage()->text('PANOPTICON_USERS_ERR_PASSWORD_MISMATCH'), 403
					);
				}

				$savedUser->setPassword($password);
			}

			// Set the Full Name
			$name = $data['name'];

			if (empty($name))
			{
				throw new RuntimeException($this->getLanguage()->text('PANOPTICON_USERS_ERR_EMPTY_NAME'), 403);
			}

			$savedUser->setName($name);

			// Set the Email address
			$email         = $data['email'];
			$validishEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

			if (!$validishEmail)
			{
				$this->container->application->enqueueMessage(
					$this->getLanguage()->sprintf('PANOPTICON_USERS_ERR_INVALID_EMAIL', htmlentities($email)), 'warning'
				);
			}

			$savedUser->setEmail($email);

			// If I am a superuser I can change the groups
			if ($myself->getPrivilege('panopticon.super'))
			{
				$savedUser->getParameters()->set('usergroups', $data['groups']);
			}

			// If I am a superuser I can change privileges
			if ($myself->getPrivilege('panopticon.super'))
			{
				$permissions = $data['permissions'];

				if ($editingMyself && $myself->getPrivilege('panopticon.super')
				    && !in_array(
						'panopticon.super', $permissions
					))
				{
					throw new RuntimeException(
						$this->getLanguage()->text('PANOPTICON_USERS_ERR_CANT_REMOVE_SELF_SUPER'), 403
					);
				}

				foreach (['super', 'admin', 'view', 'run', 'addown', 'editown'] as $k)
				{
					$savedUser->setPrivilege('panopticon.' . $k, false);
				}

				foreach ($permissions as $k)
				{
					$savedUser->setPrivilege($k, true);
				}
			}

			// Apply the parameters
			foreach ($params as $k => $v)
			{
				$savedUser->getParameters()->set($k, $v);
			}

			// Remove the password reset information
			$savedUser->getParameters()->set('pwreset.timestamp', 0);
			$savedUser->getParameters()->set('pwreset.count', 0);
			$savedUser->getParameters()->set('pwreset.secret', '');

			$this->container->userManager->saveUser($savedUser);

			// Check if this was an admin approval of a registration
			if ($wasBlocked)
			{
				$isNowBlocked = $savedUser->getParameters()->get('block', false);
				$this->getModel()->handleAdminApproval($savedUser, true, $isNowBlocked);
			}

			$status = true;

			if (method_exists($this, 'onAfterApplySave'))
			{
				$this->onAfterApplySave($data);
			}
		}
		catch (RuntimeException $e)
		{
			$status = false;
			$error  = $e->getMessage();
		}

		if (!$status)
		{
			// Cache the item data in the session. We may need to reuse them if the save fails.
			$sessionKey = $this->container->application_name . '_' . $this->viewName;
			$this->container->segment->setFlash($sessionKey, $data);

			// Redirect on error
			if ($customURL = $this->input->getBase64('returnurl', ''))
			{
				$customURL = base64_decode($customURL);
			}

			$router = $this->container->router;

			if (!empty($customURL))
			{
				$url = $customURL;
			}
			elseif ($id != 0)
			{
				$url = $router->route('index.php?view=' . $this->view . '&task=edit&id=' . $id);
			}
			else
			{
				$url = $router->route('index.php?view=' . $this->view . '&task=add');
			}

			$this->setRedirect($url, $error, 'error');
		}

		return $status;
	}

	private function isThisMyOwnUserOrAmISuper(): bool
	{
		// If I am a superuser I can change any user account
		$mySelf = $this->container->userManager->getUser();

		if ($mySelf->getPrivilege('panopticon.super'))
		{
			return true;
		}

		$model = $this->getModel();

		// If there is no record loaded, try loading a record based on the id passed in the input object
		if (!$model->getId())
		{
			$ids = $this->getIDsFromRequest($model, true);

			// No ID in the request? Force it to the current user, to make things simpler.
			if (empty($ids))
			{
				$this->input->set('id', $mySelf->getId());
				$ids = [$mySelf->getId()];
				$model->find($mySelf->getId());
			}

			if ($model->getId() != reset($ids))
			{
				return false;
			}
		}

		// If I am not a superuser I can only read my own record
		return $model->getId() == $mySelf->getId();
	}

	private function overrideRedirectForNonSuper()
	{
		// If I am a superuser I can change any user account
		$mySelf = $this->container->userManager->getUser();

		if ($mySelf->getPrivilege('panopticon.super'))
		{
			return;
		}

		$returnUrl = $this->getContainer()->router->route('index.php?view=user&task=read');
		$this->input->set('returnurl', base64_encode($returnUrl));
	}

	private function overrideRedirectForForcedMFA()
	{
		$collapseForMFA     = $this->input->get('collapseForMFA', 0);
		$collapseForPasskey = $this->input->get('collapseForPasskey', 0);

		if (!$collapseForMFA && !$collapseForPasskey)
		{
			return;
		}

		// $collapseForMFA is TRUE
		if ($collapseForMFA)
		{
			if (!$this->getContainer()->application->userNeedsMFARecords())
			{
				$returnUrl = $this->getContainer()->router->route("index.php");
				$this->input->set('returnurl', base64_encode($returnUrl));

				return;
			}

			$user      = $this->getContainer()->userManager->getUser();
			$returnUrl = $this->getContainer()->router->route(
				sprintf(
					"index.php?view=users&task=edit&id=%s&collapseForMFA=1",
					$user->getId()
				)
			);

			$this->input->set('returnurl', base64_encode($returnUrl));

			return;
		}

		// $collapseForPasskey is TRUE
		$user         = $this->getContainer()->userManager->getUser();
		$needsPasskey = count((new Authentication())->getCredentialsRepository()->getAll($user->getId())) == 0;

		if (!$needsPasskey)
		{
			$returnUrl = $this->getContainer()->router->route("index.php");
			$this->input->set('returnurl', base64_encode($returnUrl));

			return;
		}

		$user      = $this->getContainer()->userManager->getUser();
		$returnUrl = $this->getContainer()->router->route(
			sprintf(
				"index.php?view=users&task=edit&id=%s&collapseForPasskey=1",
				$user->getId()
			)
		);

		$this->input->set('returnurl', base64_encode($returnUrl));
	}
}