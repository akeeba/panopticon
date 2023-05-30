<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth\DataShape;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\DataShape\AbstractDataShape;
use InvalidArgumentException;

/**
 * @property  string $pre_message         Custom HTML to display above the MFA form
 * @property  string $field_type          How to render the MFA code field. "input" or "custom".
 * @property  string $input_type          The type attribute for the HTML input box. Typically "text" or "password".
 * @property  string $placeholder         Placeholder text for the HTML input box. Leave empty if you don't need it.
 * @property  string $label               Label to show above the HTML input box. Leave empty if you don't need it.
 * @property  string $html                Custom HTML. Only used when field_type = custom.
 * @property  string $post_message        Custom HTML to display below the MFA form
 * @property  bool   $hide_submit         Should I hide the default Submit button?
 * @property  bool   $allowEntryBatching  Is this method validating against all configured authenticators of this type?
 * @property  string $help_url            URL for help content
 *
 * @since     1.0.0
 */
class CaptiveRenderOptions extends AbstractDataShape
{
	/**
	 * Display a standard HTML5 input field. Use the input_type, placeholder and label properties to set it up.
	 *
	 * @since  1.0.0
	 */
	public const FIELD_INPUT = 'input';

	/**
	 * Display a custom HTML document. Use the html property to set it up.
	 *
	 * @since  1.0.0
	 */
	public const FIELD_CUSTOM = 'custom';

	/**
	 * Custom HTML to display above the MFA form
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $pre_message = '';

	/**
	 * How to render the MFA code field. "input" (HTML input element) or "custom" (custom HTML)
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $field_type = 'input';

	/**
	 * The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $input_type = '';

	/**
	 * Placeholder text for the HTML input box. Leave empty if you don't need it.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $placeholder = '';

	/**
	 * Label to show above the HTML input box. Leave empty if you don't need it.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $label = '';

	/**
	 * Custom HTML. Only used when field_type = custom.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $html = '';

	/**
	 * Custom HTML to display below the MFA form
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $post_message = '';

	/**
	 * Should I hide the default Submit button?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $hide_submit = false;

	/**
	 * Is this MFA method validating against all configured authenticators of the same type?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected $allowEntryBatching = false;

	/**
	 * URL for help content
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected $help_url = '';

	/**
	 * Setter for the field_type property
	 *
	 * @param   string  $value  One of self::FIELD_INPUT, self::FIELD_CUSTOM
	 *
	 * @throws  InvalidArgumentException
	 * @since   1.0.0
	 */
	protected function setField_type(string $value)
	{
		if (!in_array($value, [self::FIELD_INPUT, self::FIELD_CUSTOM]))
		{
			throw new InvalidArgumentException('Invalid value for property field_type.');
		}
	}
}