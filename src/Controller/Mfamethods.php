<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\MultiFactorAuth\Helper as MfaHelper;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Backupcodes;
use Akeeba\Panopticon\Model\Mfa;
use Awf\Container\Container;
use Awf\Mvc\Controller;
use RuntimeException;

class Mfamethods extends Controller
{
	use ACLTrait;

	public function __construct(Container $container = null)
	{
		$this->default_view = 'mfamethods';

		parent::__construct($container);

		$this->registerDefaultTask('add');
	}

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function disable(): bool
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);

		$this->_assertCanEdit($user);

		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();

		$model->deleteAll($user);

		$returnURL = $this->input->getBase64('returnurl', '');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url);

		return true;
	}

	/**
	 * Add a new MFA Method
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function add(): bool
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);

		$this->_assertCanEdit($user);

		// Also make sure the Method really does exist
		$method = $this->input->getCmd('method', '');
		$this->_assertMethodExists($method);

		if ($this->isAlreadyRegistered($method, $user_id))
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();
		$model->setState('method', $method);

		// Pass the return URL to the view
		$returnURL  = $this->input->getBase64('returnurl', '');
		$viewLayout = $this->input->getString('layout', 'form');
		$view       = $this->getView();
		$view->setLayout($viewLayout);
		$view->returnURL = $returnURL;
		$view->user      = $user;
		$view->doTask    = 'add';

		$view->setDefaultModel($model);

		$view->display();

		return true;
	}

	/**
	 * Edit an existing MFA Method
	 *
	 * @return  bool
	 * @throws \Exception
	 */
	public function edit(): bool
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the Method really does exist
		$id     = $this->input->getInt('id', 0);
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();
		$model->setState('id', $id);

		// Pass the return URL to the view
		$returnURL  = $this->input->getBase64('returnurl', '');
		$viewLayout = $this->input->getString('layout', 'form');
		$view       = $this->getView();
		$view->setLayout($viewLayout);
		$view->returnURL = $returnURL;
		$view->user      = $user;
		$view->doTask    = 'edit';

		$view->setDefaultModel($model);

		$view->display();

		return true;
	}

	/**
	 * Save the MFA Method
	 *
	 * @return  bool
	 */
	public function save()
	{
		$this->assertLoggedInUser();

		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);
		$this->_assertCanEdit($user);

		// Redirect
		$url       = $this->container->router->route('index.php?view=users&task=edit&id=' . $user_id);
		$returnURL = $this->input->getBase64('returnurl', '');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		// The record must either be new (ID zero) or exist
		$id     = $this->input->getInt('id', 0);
		$record = $this->_assertValidRecordId($id, $user);

		// If it's a new record we need to read the Method from the request and update the (not yet created) record.
		if ($record->id == 0)
		{
			$methodName = $this->input->getCmd('method', '');
			$this->_assertMethodExists($methodName);
			$record->method = $methodName;

			if ($this->isAlreadyRegistered($methodName, $user_id))
			{
				throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
			}
		}

		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();

		// Ask the plugin to validate the input by calling onLoginGuardMfaSaveSetup
		$result = [];
		$input  = $this->input;

		try
		{
			$pluginResults = $this->container->eventDispatcher->trigger('onMfaSaveSetup', [$record, $input]);

			foreach ($pluginResults as $pluginResult)
			{
				$result = array_merge($result, $pluginResult);
			}
		}
		catch (RuntimeException $e)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?&view=mfamethod&task=';

			if ($id)
			{
				$nonSefUrl .= 'edit&id=' . (int) $id;
			}
			else
			{
				$nonSefUrl .= 'add&method=' . $record->method;
			}

			$nonSefUrl .= '&user_id=' . $user_id;

			if (!empty($returnURL))
			{
				$nonSefUrl .= '&returnurl=' . urlencode($returnURL);
			}

			$url = $this->container->router->route($nonSefUrl);
			$this->setRedirect($url, $e->getMessage(), 'error');

			return true;
		}

		// Update the record's options with the plugin response
		$title = $this->input->getString('title', '');
		$title = trim($title);

		if (empty($title))
		{
			$method = $model->getMethod($record->method);
			$title  = $method['display'];
		}

		// Update the record's "default" flag
		$default         = $this->input->getBool('default', false);
		$record->title   = $title;
		$record->options = json_encode($result);
		$record->default = $default ? 1 : 0;

		// Ask the model to save the record
		try
		{
			$record->save();
			$saved = true;
			$error = '';
		}
		catch (\Exception $e)
		{
			$saved = false;
			$error = $e->getMessage();
		}

		if (!$saved)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?view=mfamethod&task=';

			if ($id)
			{
				$nonSefUrl .= 'edit&id=' . (int) $id;
			}
			else
			{
				$nonSefUrl .= 'add';
			}

			$nonSefUrl .= '&user_id=' . $user_id;

			if (!empty($returnURL))
			{
				$nonSefUrl .= '&returnurl=' . urlencode($returnURL);
			}

			$url = $this->container->router->route($nonSefUrl);
			$this->setRedirect($url, $error, 'error');

			return true;
		}

		$this->setRedirect($url);

		return true;
	}

	/**
	 * Regenerate backup codes
	 *
	 * @return  bool
	 */
	public function regenbackupcodes(): bool
	{
		$this->assertLoggedInUser();

		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);
		$this->_assertCanEdit($user);

		/** @var Backupcodes $model */
		$model = $this->getModel('Backupcodes');
		$model->regenerateBackupCodes($user);

		$backupCodesRecord = $model->getBackupCodesRecord($user);

		// Redirect
		$redirectUrl = sprintf("index.php?view=mfamethod&task=edit&user_id=%s&id=%s", $user_id, $backupCodesRecord->id);
		$returnURL   = $this->input->getBase64('returnurl', '');

		if (!empty($returnURL))
		{
			$redirectUrl .= '&returnurl=' . $returnURL;
		}

		$this->setRedirect($this->container->router->route($redirectUrl));

		return true;
	}

	/**
	 * Delete an existing MFA Method
	 *
	 * @return  bool
	 */
	public function delete(): bool
	{
		$this->assertLoggedInUser();

		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', 0);
		$user    = $this->container->userManager->getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the Method really does exist
		$id     = $this->input->getInt('id', 0);
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		$type    = null;
		$message = null;

		try
		{
			$record->delete();
		}
		catch (\Exception $e)
		{
			$message = $e->getMessage();
			$type    = 'error';
		}

		// Redirect
		$url       = $this->container->router->route(
			'index.php?view=users&task=edit&id==' . $user_id, false
		);
		$returnURL = $this->input->getBase64('returnurl', '');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url, $message, $type);

		return true;
	}

	/**
	 * Assert that the provided ID is a valid record identified for the given user
	 *
	 * @param   int        $id    Record ID to check
	 * @param   User|null  $user  User record. Null to use current user.
	 *
	 * @return  Mfa  The loaded record
	 *
	 */
	private function _assertValidRecordId(int $id, ?User $user = null): Mfa
	{
		$user ??= $this->container->userManager->getUser();

		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();

		$model->setState('id', $id);

		$record = $model->getRecord($user);

		if (($record->id != $id) || ($record->user_id != $user->getId()))
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		return $record;
	}

	/**
	 * Assert that the user is logged in.
	 *
	 * @param   User|null  $user  User record. Null to use current user.
	 */
	private function _assertCanEdit(User $user = null): void
	{
		$user ??= $this->container->userManager->getUser();

		if (!MfaHelper::canEditUser($user))
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	/**
	 * Assert that the specified MFA Method exists, is activated and enabled for the current user
	 *
	 * @param   string|null  $method  The Method to check
	 *
	 * @return  void
	 *
	 */
	private function _assertMethodExists(?string $method): void
	{
		/** @var \Akeeba\Panopticon\Model\Mfamethods $model */
		$model = $this->getModel();

		if (empty($method) || !$model->methodExists($method))
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	private function assertLoggedInUser(): void
	{
		$user = $this->container->userManager->getUser();

		if ($user->getId() <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	private function isAlreadyRegistered(string $method, int $user_id): bool
	{
		$allMethods = MfaHelper::getMfaMethods();
		$thisMethod = $allMethods[$method];
		$allowMultiple = $thisMethod->allowMultiple;

		if ($allowMultiple)
		{
			return false;
		}

		$existingRecords = array_filter(
			MfaHelper::getUserMfaRecords($this->container, $user_id),
			fn(Mfa $x) => $x->method === $method
		);

		return count($existingRecords) !== 0;
	}
}