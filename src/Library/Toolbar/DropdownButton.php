<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Toolbar;

defined('AKEEBA') || die;

use Awf\Document\Toolbar\Button;

/**
 * A dropdown menu button for the AWF toolbar.
 *
 * This is just the data abstraction of the drop-down. The rendering takes place in the DefaultTemplate helper.
 *
 * @since  1.0.5
 * @see    \Akeeba\Panopticon\Helper\DefaultTemplate::getRenderedToolbarButtons
 * @see    \Akeeba\Panopticon\Helper\DefaultTemplate::getRenderedDropdownButtonMenu
 */
class DropdownButton extends Button
{
	/**
	 * The buttons in the drop-down
	 *
	 * @var    Button[]
	 * @since  1.0.5
	 */
	private array $buttons = [];

	/**
	 * Adds a button to the drop-down
	 *
	 * @param   Button  $button
	 *
	 * @return  self
	 * @since   1.0.5
	 */
	public function addButton(Button $button): self
	{
		if (in_array($button, $this->buttons))
		{
			return $this;
		}

		$this->buttons[] = $button;

		return $this;
	}

	/**
	 * Removes a button from the drop-down
	 *
	 * @param   Button  $button
	 *
	 * @return  self
	 * @since   1.0.5
	 */
	public function removeButton(Button $button): self
	{
		if (!in_array($button, $this->buttons))
		{
			return $this;
		}

		$idx = array_search($button, $this->buttons);

		unset ($this->buttons[$idx]);

		$this->buttons = array_values($this->buttons);

		return $this;
	}

	/**
	 * Returns the buttons included in the drop-down
	 *
	 * @return  Button[]
	 * @since   1.0.5
	 */
	public function getButtons(): array
	{
		return $this->buttons;
	}
}