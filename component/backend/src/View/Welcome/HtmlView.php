<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Administrator\View\Welcome;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\User;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Throwable;

class HtmlView extends BaseHtmlView
{
	/**
	 * Is the "Web Services - Panopticon" plugin installed and enabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $isWebServicesPluginEnabled = false;

	/**
	 * Is the "API Authentication - Web Services Joomla Token" plugin installed and enabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $isTokenAuthPluginEnabled = false;

	/**
	 * Is the "User - Joomla API Token" plugin installed and enabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $isUserTokenPluginEnabled = false;

	/**
	 * Is the "User - Joomla API Token" plugin installed and enabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $isAllowedUser = false;

	/**
	 * Has the user created an API token (and not disabled the API token for their account)?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $hasToken = false;

	public function display($tpl = null)
	{
		ToolbarHelper::title(
			Text::_('COM_PANOPTICON'),
			'plug'
		);

		$this->isWebServicesPluginEnabled = PluginHelper::isEnabled('webservices', 'panopticon');
		$this->isTokenAuthPluginEnabled   = PluginHelper::isEnabled('api-authentication', 'token');
		$this->isUserTokenPluginEnabled   = PluginHelper::isEnabled('user', 'token');
		$this->hasToken                   = !empty($this->getApiToken());
		$this->isAllowedUser              = $this->isAllowedUser();


		parent::display($tpl);
	}

	protected function getApiToken(?User $user = null): string
	{
		$user = $user ?? Factory::getApplication()->getIdentity();

		if (empty($user) || $user->guest)
		{
			return '';
		}

		$tokenSeed    = $this->getUserProfileValue($user, 'joomlatoken.token');
		$tokenEnabled = $this->getUserProfileValue($user, 'joomlatoken.enabled', 1);

		if (empty($tokenSeed) || !$tokenEnabled)
		{
			return '';
		}

		try
		{
			$siteSecret = Factory::getApplication()->get('secret');
		}
		catch (Exception $e)
		{
			$siteSecret = '';
		}

		// NO site secret? You monster!
		if (empty($siteSecret))
		{
			return '';
		}

		$algorithm = 'sha256';
		$userId    = $user->id;
		$rawToken  = base64_decode($tokenSeed);
		$tokenHash = hash_hmac($algorithm, $rawToken, $siteSecret);
		$message   = base64_encode("$algorithm:$userId:$tokenHash");

		return $message;
	}

	private function getUserProfileValue(User $user, string $key, $default = null)
	{
		$id = $user->id;

		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('profile_value'))
			->from($db->quoteName('#__user_profiles'))
			->where($db->quoteName('profile_key') . ' = :key')
			->where($db->quoteName('user_id') . ' = :id')
			->bind(':key', $key, ParameterType::STRING)
			->bind(':id', $id, ParameterType::INTEGER);

		try
		{
			return $db->setQuery($query)->loadResult() ?? $default;
		}
		catch (Throwable $e)
		{
			return $default;
		}
	}

	private function isAllowedUser(): bool
	{
		if (!$this->isUserTokenPluginEnabled)
		{
			return false;
		}

		$plugin            = PluginHelper::getPlugin('user', 'token');
		$params            = new Registry($plugin->params ?? '{}');
		$allowedUserGroups = $params->get('allowedUserGroups', [8]);
		$allowedUserGroups = is_array($allowedUserGroups)
			? $allowedUserGroups
			: ArrayHelper::toInteger(explode(',', $allowedUserGroups));

		$user = Factory::getApplication()->getIdentity();

		return !empty(array_intersect($user->getAuthorisedGroups(), $allowedUserGroups));
	}
}