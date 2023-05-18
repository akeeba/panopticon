<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

$config = $this->getContainer()->appConfig->toString('Php', ['class' => 'AConfig', 'closingtag' => false]);
$config = substr($config, 5);

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
   [...document.querySelectorAll('.copyButton')].forEach(elButton => {
       elButton.addEventListener('click', e => {
           e.preventDefault();
           const sourceId = elButton.dataset.copySource;
           if (!sourceId) return;
           const elSource = document.getElementById(sourceId);
           if (!elSource) return;
           elSource.classList.add('text-success');
           navigator.clipboard.writeText(elSource.innerText)
           .then(() => {
               setTimeout(() => {
					elSource.classList.remove('text-success');                   
               }, 100)
           });
       })
   });
});

JS;
$this->container->application->getDocument()->addScriptDeclaration($js);
?>

<div class="alert alert-warning alert-dismissible" role="alert">
	<h3 class="alert-heading">
		<?= Text::_('PANOPTICON_SETUP_LBL_NO_CONFIG_WRITTEN_ALERT_HEAD') ?>
	</h3>
	<p>
		<?= Text::_('PANOPTICON_SETUP_LBL_NO_CONFIG_WRITTEN_ALERT_BODY') ?>
	</p>
</div>

<div class="card my-3">
	<h3 class="card-header">
		<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_HEAD') ?>
	</h3>
	<div class="card-body">
		<ol>
			<li class="mb-2">
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP1') ?>
				<br/><span class="text-muted">
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP1_DETAIL') ?>
				</span>
			</li>
			<li class="mb-2">
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP2') ?>
			</li>
			<li class="mb-2">
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP3') ?>
			</li>
			<li class="mb-2">
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP4') ?>
				<br/><span class="text-muted">
					<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP4_DETAIL') ?>
				</span>
			</li>
			<li>
				<?= Text::_('PANOPTICON_SETUP_LBL_NOCONF_INSTRUCTIONS_STEP5') ?>
			</li>
		</ol>
		<div class="my-2 d-flex flex-row gap-2">
			<button type="button" class="btn btn-secondary copyButton"
			        data-copy-source="configSource">
				<span class="fa fa-clipboard" aria-hidden="true"></span>
				<?= Text::_('PANOPTICON_SETUP_BTN_COPY_TO_CLIPBOARD') ?>
			</button>

			<a href="<?= $this->container->router->route('index.php?view=setup&task=cron') ?>"
			   class="btn btn-outline-primary" role="button">
				<span class="fa fa-chevron-right" aria-hidden="true"></span>
				<?= Text::_('PANOPTICON_BTN_NEXT') ?>
			</a>
		</div>
		<pre class="bg-light-subtle border p-2 m-1 rounded-2" id="configSource">
&lt;?php
defined('AKEEBA') || die;
<?= $this->escape($config) ?>
		</pre>
	</div>
</div>