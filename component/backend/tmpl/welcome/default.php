<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

?>
	<div class="text-center mt-2 mb-4 px-4">
		<span class="fa-4x mb-2 icon-plug article" aria-hidden="true"></span>
		<h2 class="display-5 fw-bold"><?= Text::_('COM_PANOPTICON_WELCOME_TITLE') ?></h2>
		<p class="fs-3 text-muted"><?= Text::_('COM_PANOPTICON_WELCOME_CONTENT') ?></p>
	</div>

<?php
echo $this->loadTemplate('plgauth');
echo $this->loadTemplate('plgusertoken');
echo $this->loadTemplate('hastoken');
echo $this->loadTemplate('plgwebservices');
echo $this->loadTemplate('info');
