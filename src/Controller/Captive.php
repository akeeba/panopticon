<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Backupcodes;
use Awf\Container\Container;
use Awf\Mvc\Controller;
use Awf\Uri\Uri;
use RuntimeException;

class Captive extends Controller
{
	use ACLTrait;

	public function __construct(Container $container = null)
	{
		parent::__construct($container);

		$this->registerDefaultTask('captive');
	}

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function select()
	{
		$this->input->set('layout', 'select');

		return $this->captive();
	}

	public function captive(): bool
	{
		$user = $this->container->userManager->getUser();

		$this->container->application->getDocument()->getMenu()->disableMenu('main');

		// Only allow logged-in Users
		if ($user->getId() <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		// Get the view object
		$viewLayout = $this->input->getString('layout', 'default');
		$view       = $this->getView();
		$view->setLayout($viewLayout);

		// If we're already logged in go to the site's home page
		if ($this->container->segment->get('panopticon.mfa_checked', 0) == 1)
		{
			$this->setRedirect($this->container->router->route('index.php'));

			return true;
		}

		// Pass the model to the view
		/** @var \Akeeba\Panopticon\Model\Captive $model */
		$model = $this->getModel();
		$view->setDefaultModel($model);

		/** @var Backupcodes $codesModel */
		$codesModel = $this->getModel('Backupcodes');
		$view->setModel('Backupcodes', $codesModel);

		// Pass the MFA record ID to the model
		$record_id = $this->input->getInt('record_id', null);
		$model->setState('record_id', $record_id);

		$view->setTask('captive');
		$view->setDoTask('captive');
		$view->display();

		return true;
	}

	/**
	 * Validate the MFA code entered by the user
	 *
	 * @return  bool
	 */
	public function validate(): bool
	{
		// CSRF Check
		$this->csrfProtection();

		$this->container->application->getDocument()->getMenu()->disableMenu('main');

		// Get the MFA parameters from the request
		$record_id  = $this->input->getInt('record_id', null);
		$code       = $this->input->getRaw('code', null);

		/** @var \Akeeba\Panopticon\Model\Captive $model */
		$model = $this->getModel();

		// Validate the MFA record
		$model->setState('record_id', $record_id);
		$record = $model->getRecord();

		if (empty($record))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_MFA_ERR_INVALID_METHOD'), 500);
		}

		// Validate the code
		$user = $this->container->userManager->getUser();

		$results = $this->container->eventDispatcher->trigger('onMfaValidate', [$record, $user, $code]);
		$isValidCode = false;

		if ($record->method == 'backupcodes')
		{
			/** @var Backupcodes $codesModel */
			$codesModel = $this->getModel('Backupcodes');
			$results    = [$codesModel->isBackupCode($code, $user)];
			/**
			 * This is required! Do not remove!
			 *
			 * There is a store() call below. It saves the in-memory MFA record to the database. That includes the
			 * options key which contains the configuration of the Method. For backup codes, these are the actual codes
			 * you can use. When we check for a backup code validity we also "burn" it, i.e. we remove it from the
			 * options table and save that to the database. However, this DOES NOT update the $record here. Therefore,
			 * the call to save() would overwrite the database contents with a record which _includes_ the backup code
			 * we just burnt. As a result the single use backup codes end up being multiple use.
			 *
			 * By doing a getRecord() here, right after we have "burned" any correct backup codes, we resolve this
			 * issue. The loaded record will reflect the database contents where the options DO NOT include the code we
			 * just used. Therefore, the call to save() will result in the correct database state, i.e. the used backup
			 * code being removed.
			 */
			$record = $model->getRecord();
		}

		$isValidCode = in_array(true, $results ?: []);

		if (!$isValidCode)
		{
			// The code is wrong. Display an error and go back.
			$captiveURL = $this->container->router->route(
				sprintf(
					'index.php?view=captive&record_id=%d',
					$record_id
				)
			);
			$message    = $this->getLanguage()->text('PANOPTICON_MFA_ERR_INVALID_CODE');
			$this->setRedirect($captiveURL, $message, 'error');

			// Optionally count this as a login failure
			if ($this->getContainer()->appConfig->get('mfa_counts_as_login_failure', 0))
			{
				$loginFailureModel = $this->getContainer()->mvcFactory->makeModel('Loginfailures');
				$loginFailureModel->logFailure(true);
			}

			// Apply Maximum MFA tries
			$maxMFATries  = (int) $this->getContainer()->appConfig->get('mfa_max_tries', 3);
			$maxMFATries  = min(max($maxMFATries, 1), 10000);
			$currentTries = (int) $this->getContainer()->segment->get('panopticon.mfa.tries', 0);

			$this->getContainer()->segment->set('panopticon.mfa.tries', ++$currentTries);

			if ($currentTries >= $maxMFATries)
			{
				$logger   = $this->container->loggerFactory->get('login');
				$manager  = $this->container->userManager;
				$username = $manager->getUser()->getUsername();
				$router   = $this->container->router;

				$logger->info('Logged out (maximum MFA tries)', ['username' => $username]);
				$manager->logoutUser();

				$this->setRedirect(
					$router->route('index.php?view=login'),
					$this->getContainer()->language->text('PANOPTICON_APP_ERR_MFA_LOGOUT')
				);
			}

			return true;
		}
		
		// Update the Last Used, UA and IP columns
		$jNow = $this->container->dateFactory();

		$record->last_used = $jNow->toSql();
		$record->save();

		// Flag the user as fully logged in
		$sessionSegment = $this->container->segment;
		$sessionSegment->set('panopticon.mfa_checked', 1);

		// Get the return URL stored by the plugin in the session
		$return_url = $sessionSegment->get('com_loginguard.return_url', '');

		// If the return URL is not set or not inside this site redirect to the site's front page
		if (empty($return_url) || !Uri::isInternal($return_url))
		{
			$return_url = Uri::base();
		}

		$this->setRedirect($return_url);

		return true;
	}
}