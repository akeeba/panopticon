<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\Panopticon\Administrator\View\Welcome\HtmlView $this */

$user = Factory::getApplication()->getIdentity();

if (!$user->authorise('core.manage') || $this->isUserTokenPluginEnabled)
{
	return;
}

?>
<div class="alert alert-danger">
	<h3 class="alert-heading">
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NO_USER_PLG_TITLE') ?>
	</h3>
	<p>
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NO_USER_PLG_DETAILS') ?>
	</p>
	<p>
		<a
			class="btn btn-primary"
			href="index.php?option=com_plugins&view=plugins&filter[folder]=user&filter[element]=token&filter[enabled]=0&filter[access]=&filter[search]=">
			<span class="icon-eye-open" aria-hidden="true"></span>
			<?= Text::_('COM_PANOPTICON_WELCOME_ERR_COMMON_ACTION') ?>
		</a>
	</p>
</div>
