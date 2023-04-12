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

if ($this->hasToken || !$this->isTokenAuthPluginEnabled || !$this->isUserTokenPluginEnabled || !$this->isAllowedUser)
{
	return;
}

$editURL = sprintf(
	'index.php?option=com_users&task=user.edit&id=%d&return=%s',
	$user->id,
	base64_encode('index.php?option=com_panopticon')
)

?>
<div class="alert alert-danger">
	<h3 class="alert-heading">
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NOTOKEN_TITLE') ?>
	</h3>
	<p>
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NOTOKEN_DETAILS') ?>
	</p>
	<p>
		<a
			class="btn btn-primary"
			href="<?= $editURL ?>">
			<span class="icon-user" aria-hidden="true"></span>
			<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NOTOKEN_ACTION') ?>
		</a>
	</p>
</div>
