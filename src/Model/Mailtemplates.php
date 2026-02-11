<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Html2Text;
use Awf\Container\Container;
use Awf\Mvc\DataModel;

/**
 * Mail Templates
 *
 * @property int    $id
 * @property string $type
 * @property string $language
 * @property string $subject
 * @property string $html
 * @property string $plaintext
 */
class Mailtemplates extends DataModel
{
	private const CSS_KEY = 'mail.common_css';

	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__mailtemplates';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$search = trim($this->getState('search', null) ?? '');

		if (!empty($search))
		{
			$query->extendWhere(
				'AND', [
				$query->quoteName('type') . ' LIKE ' . $query->quote('%' . $search . '%'),
				$query->quoteName('subject') . ' LIKE ' . $query->quote('%' . $search . '%'),
			], 'OR'
			);
		}

		return $query;
	}

	public function getCommonCSS(): ?string
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote(self::CSS_KEY));

		return $db->setQuery($query)->loadResult() ?: '';
	}

	public function setCommonCSS(?string $css): void
	{
		$css ??= '';

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->columns([
				$db->quoteName('key'),
				$db->quoteName('value'),
			])
			->values(
				$db->quote(self::CSS_KEY) . ',' . $db->quote($css)
			);

		$db->setQuery($query)->execute();
	}

	public function check()
	{
		if (empty($this->plaintext) && !empty($this->html))
		{
			$convert = new Html2Text($this->html);
			$this->plaintext = $convert->getText();
		}

		return parent::check();
	}


	public static function getMailTypeOptions(): array
	{
		return [
			''                          => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_MUST_SELECT',
			'joomlaupdate_found'        => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_JOOMLAUPDATE_FOUND',
			'joomlaupdate_will_install' => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_JOOMLAUPDATE_WILL_INSTALL',
			'joomlaupdate_installed'    => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_JOOMLAUPDATE_INSTALLED',
			'joomlaupdate_failed'       => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_JOOMLAUPDATE_FAILED',
			'extension_update_found'    => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_EXTENSION_UPDATE_FOUND',
			'extensions_update_done'    => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_EXTENSIONS_UPDATE_DONE',
			'plugin_update_found'       => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_PLUGIN_UPDATE_FOUND',
			'plugins_update_done'       => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_PLUGINS_UPDATE_DONE',
			'akeebabackup_success'      => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_AKEEBABACKUP_SUCCESS',
			'akeebabackup_fail'         => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_AKEEBABACKUP_FAIL',
			'selfupdate_found'          => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_SELFUPDATE_FOUND',
			'scheduled_update_summary'  => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_SCHEDULED_UPDATE_SUMMARY',
			'action_summary'            => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_ACTION_SUMMARY',
			'ssl_tls_expiring'          => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_SSL_TLS_EXPIRING',
			'site_down'                 => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_SITE_DOWN',
			'site_up'                   => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_SITE_UP',
			'domain_expiring'           => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_DOMAIN_EXPIRING',
			'pwreset'                   => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_PWRESET',
			'registration_pending_admin' => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_REGISTRATION_PENDING_ADMIN',
			'registration_activate'     => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_REGISTRATION_ACTIVATE',
			'registration_approved'     => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_REGISTRATION_APPROVED',
			'registration_expired'      => 'PANOPTICON_MAILTEMPLATES_OPT_TYPE_REGISTRATION_EXPIRED',
		];
	}
}