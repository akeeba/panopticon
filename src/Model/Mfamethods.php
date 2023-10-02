<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\MultiFactorAuth\Helper;
use Akeeba\Panopticon\Library\User\User;
use Awf\Mvc\DataModel;
use Awf\Mvc\Model;
use Awf\Text\Text;
use Exception;
use RuntimeException;

defined('AKEEBA') || die;

/**
 * MFA Methods Model
 *
 * @since  1.0.0
 */
class Mfamethods extends Model
{
	/**
	 * List of MFA methods
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	protected $mfaMethods = null;

	/**
	 * Returns a list of all available and their currently active records for given user.
	 *
	 * @param   User|null  $user  The user object. Skip to use the current user.
	 *
	 * @return  array
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getMethods(?User $user = null): array
	{
		$user ??= $this->container->userManager->getUser();

		if ($user->getId() <= 0)
		{
			return [];
		}

		// Get an associative array of MFA methods
		$rawMethods = Helper::getMfaMethods();
		$methods    = [];

		foreach ($rawMethods as $method)
		{
			$method->active = [];
			$methods[$method->name] = $method;
		}

		// Put the user MFA records into the methods array
		$userMfaRecords = Helper::getUserMfaRecords($this->getContainer(), $user->getId());

		if (!empty($userMfaRecords))
		{
			foreach ($userMfaRecords as $record)
			{
				if (!isset($methods[$record->method]))
				{
					continue;
				}

				// We have to do this because you can't update the value returned by a magic __get or offsetGet.
				$active = $methods[$record->method]->active;
				$active[$record->id] = $record;
				$methods[$record->method]->active = $active;
			}
		}

		return $methods;
	}

	/**
	 * Delete all MFA methods for the given user.
	 *
	 * @param   User|null  $user  The user object to reset TSV for. Null to use the current user.
	 *
	 * @return  void
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function deleteAll(?User $user = null): void
	{
		$user ??= $this->container->userManager->getUser();

		if ($user->getId() <= 0)
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__mfa'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->getId()));
		$db->setQuery($query)->execute();
	}

	/**
	 * Set the user's "don't show this again" flag.
	 *
	 * @param   User  $user  The user to check
	 * @param   bool  $flag  True to set the flag, false to unset it (it will be set to 0, actually)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setFlag(User $user, bool $flag = true): void
	{
		$user->getParameters()->set('mfa.dontshow', $flag);

		$this->container->userManager->saveUser($user);
	}

	/**
	 * Is the specified MFA method available?
	 *
	 * @param   string  $method  The method to check.
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function methodExists(string $method): bool
	{
		if (!is_array($this->mfaMethods))
		{
			$this->populateMfaMethods();
		}

		return isset($this->mfaMethods[$method]);
	}

	/**
	 * Get the specified MFA method's record
	 *
	 * @param   string  $method  The method to retrieve.
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public function getMethod(string $method): array
	{
		if (!$this->methodExists($method))
		{
			return [
				'name'          => $method,
				'display'       => '',
				'shortinfo'     => '',
				'image'         => '',
				'canDisable'    => true,
				'allowMultiple' => true,
			];
		}

		return $this->mfaMethods[$method];
	}

	/**
	 * Get the specified MFA record.
	 *
	 * It will return a fake default record when no record ID is specified.
	 *
	 * @param   User|null  $user  The user record. Null to use the currently logged-in user.
	 *
	 * @return  Mfa
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getRecord(User $user = null): Mfa
	{
		$user ??= $this->container->userManager->getUser();

		$defaultRecord = $this->getDefaultRecord($user);
		$id            = (int) $this->getState('id', 0);

		if ($id <= 0)
		{
			return $defaultRecord;
		}

		/** @var Mfa $record */
		$record = $this->getContainer()->mvcFactory->makeTempModel('Mfa');

		try
		{
			$record->findOrFail(
				[
					'user_id' => $user->getId(),
					'id'      => $id,
				]
			);
		}
		catch (Exception $e)
		{
			return $defaultRecord;
		}

		return $this->methodExists($record->method) ? $record : $defaultRecord;

	}

	/**
	 * @param   User|null  $user  The user record. Null to use the currently logged-in user.
	 *
	 * @return  array
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getRenderOptions(?User $user = null): array
	{
		$user ??= $this->container->userManager->getUser();

		$renderOptions = [
			// Default title if you are setting up this MFA method for the first time
			'default_title'  => '',
			// Custom HTML to display above the MFA setup form
			'pre_message'    => '',
			// Heading for displayed tabular data. Typically used to display a list of fixed MFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => [],
			// How to render the MFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically, "text" or "password". Use any HTML5 input type.
			'input_type'     => 'text',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => '',
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the MFA setup)?
			'show_submit'    => true,
			// onclick handler for the submit button (apply the MFA setup)
			'submit_onclick' => '',
			// Additional CSS classes for the submit button (apply the MFA setup)
			'submit_class'   => '',
			// Custom HTML to display below the MFA setup form
			'post_message'   => '',
			// A URL with help content for this method to display to the user
			'help_url'       => '',
		];

		$record     = $this->getRecord($user);
		$dispatcher = $this->container->eventDispatcher;
		$results    = $dispatcher->trigger('onMfaGetSetup', [$record]);

		if (empty($results))
		{
			return $renderOptions;
		}

		foreach ($results as $result)
		{
			if (empty($result))
			{
				continue;
			}

			return array_merge($renderOptions, $result);
		}

		return $renderOptions;
	}

	/**
	 * @param   User|null  $user  The user record. Null to use the current user.
	 *
	 * @return  Mfa
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	protected function getDefaultRecord(User $user = null): Mfa
	{
		$user ??= $this->container->userManager->getUser();

		$method = $this->getState('method');
		$title  = '';

		if (is_null($this->mfaMethods))
		{
			$this->populateMfaMethods();
		}

		if ($method && isset($this->mfaMethods[$method]))
		{
			$title = $this->mfaMethods[$method]->display;
		}

		$record = $this->getContainer()->mvcFactory->makeTempModel('Mfa');

		$record->bind(
			[
				'id'      => null,
				'user_id' => $user->getId(),
				'title'   => $title,
				'method'  => $method,
				'default' => 0,
				'options' => [],
			]
		);

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $record;
	}

	/**
	 * Populate the list of MFA methods
	 *
	 * @since   1.0.0
	 */
	private function populateMfaMethods(): void
	{
		$this->mfaMethods = [];
		$mfaMethods       = Helper::getMfaMethods();

		if (empty($mfaMethods))
		{
			return;
		}

		foreach ($mfaMethods as $method)
		{
			$this->mfaMethods[$method->name] = $method;
		}

		// We also need to add the backup codes method
		$this->mfaMethods['backupcodes'] = [
			'name'          => 'backupcodes',
			'display'       => Text::_('PANOPTICON_MFA_LBL_BACKUPCODES'),
			'shortinfo'     => Text::_('PANOPTICON_MFA_LBL_BACKUPCODES_DESCRIPTION'),
			'image'         => 'media/mfa/images/emergency.svg',
			'canDisable'    => false,
			'allowMultiple' => false,
		];
	}
}