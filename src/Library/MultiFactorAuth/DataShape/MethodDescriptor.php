<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth\DataShape;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\DataShape\AbstractDataShape;
use Akeeba\Panopticon\Model\Mfa;

/**
 * @property string $name                Internal code of this MFA Method
 * @property string $display             User-facing name for this MFA Method
 * @property string $shortinfo           Short description of this MFA Method displayed to the user
 * @property string $image               URL to the logo image for this Method
 * @property bool   $canDisable          Are we allowed to disable it?
 * @property bool   $allowMultiple       Are we allowed to have multiple instances of it per user?
 * @property string $help_url            URL for help content
 * @property bool   $allowEntryBatching  Allow authentication against all entries of this MFA Method.
 *
 * @since       1.0.0
 */
class MethodDescriptor extends AbstractDataShape
{
	/**
	 * Internal code of this MFA Method
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $name = '';

	/**
	 * User-facing name for this MFA Method
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $display = '';

	/**
	 * Short description of this MFA Method displayed to the user
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $shortinfo = '';

	/**
	 * URL to the logo image for this Method
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $image = '';

	/**
	 * Are we allowed to disable it?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $canDisable = true;

	/**
	 * Are we allowed to have multiple instances of it per user?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $allowMultiple = false;

	/**
	 * URL for help content
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $help_url = '';

	/**
	 * Allow authentication against all entries of this MFA Method.
	 *
	 * Otherwise authentication takes place against a SPECIFIC entry at a time.
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $allowEntryBatching = false;

	/**
	 * Active authentication methods, used internally only
	 *
	 * @var   Mfa[]
	 * @since 1.0.0
	 * @internal
	 */
	protected $active = [];

	public function addActiveMethod(Mfa $record)
	{
		$this->active[$record->id] = $record;
	}
}