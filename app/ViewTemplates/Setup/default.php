<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBA') or die;

use Awf\Text\Text;
use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */
?>

<div class="vh-100 d-flex flex-column justify-content-center">
	<div class="px-4 py-5 my-5 text-center">
		<img class="d-block mx-auto mb-4" src="<?= Uri::base() ?>media/images/logo_colour.svg" alt="" aria-hidden="true" style="height: 4em">
		<h3 class="display-5 fw-bold">
			<?= Text::sprintf('PANOPTICON_SETUP_LBL_HEAD_WELCOME', Text::_('PANOPTICON_APP_TITLE_SHORT')) ?>
		</h3>
		<div class="col-lg-6 mx-auto">
			<p class="lead mb-4">
				<?= Text::_('PANOPTICON_SETUP_LBL_WELCOME_HERO_TEXT') ?>
			</p>
			<div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
				<a href="<?= $this->container->router->route('index.php?view=setup&task=precheck&layout=precheck') ?>" role="button" class="btn btn-primary btn-lg px-4 gap-3 text-white">
					<?= Text::_('PANOPTICON_SETUP_BTN_LETS_GO') ?>
					<span class="fa fa-chevron-circle-right" aria-hidden="true"></span>
				</a>
			</div>
		</div>
	</div>
</div>


