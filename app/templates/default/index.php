<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\DarkModeEnum;
use Akeeba\Panopticon\Helper\DefaultTemplate as TemplateHelper;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Text\Text;
use Awf\Uri\Uri;

/** @var Awf\Document\Document $this */

[$langCode,] = explode('-', Text::detectLanguage() ?: 'en-GB');
$user = $this->container->userManager->getUser();
$darkMode = TemplateHelper::getDarkMode();
$darkModeValue = match ($darkMode)
{
	DarkModeEnum::DARK => 'dark',
	DarkModeEnum::LIGHT => 'light',
	default => ''
};
$versionTag = Version::create(AKEEBA_PANOPTICON_VERSION)->tagType();

TemplateHelper::applyFontSize();
TemplateHelper::applyDarkModeJavaScript();

$isBareDisplay = $this->getContainer()->input->getCmd('tmpl', '') === 'component';
?>
<html lang="<?= $langCode ?>" data-bs-theme="<?= $darkModeValue ?>>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= Text::_('PANOPTICON_APP_TITLE') ?></title>

	<?php // See https://medium.com/swlh/are-you-using-svg-favicons-yet-a-guide-for-modern-browsers-836a6aace3df ?>
	<link rel="icon" href="<?= Uri::base() ?>media/images/logo_colour.svg">
	<link rel="mask-icon" href="<?= Uri::base() ?>media/images/logo_bw.svg" color="#000000">

	<link rel="stylesheet" href="<?= Uri::base() ?>media/css/theme.min.css" />

	<?php include __DIR__ . '/includes/head.php' ?>
</head>
<body>

<header class="container-xl p-0">
	<?php // Top header ?>
	<?php if (!$isBareDisplay): ?>
	<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom border-2" id="topNavbar">
		<h1>
			<a class="navbar-brand ps-2 d-flex flex-row"
			   href="<?= $this->getMenu()->isEnabled('main') ? Uri::base() : 'javascript:' ?>">
				<?= file_get_contents(APATH_MEDIA . '/images/logo_colour.svg') ?>
				<div>
					<?= Text::_('PANOPTICON_APP_TITLE_SHORT') ?>
					<?php if (in_array($versionTag, [Version::TAG_TYPE_ALPHA, Version::TAG_TYPE_BETA, Version::TAG_TYPE_RELEASE_CANDIDATE, Version::TAG_TYPE_DEV])): ?>
						<sup>
							<span class="badge bg-danger-subtle"><?= ucfirst($versionTag) ?></span>
						</sup>
					<?php endif ?>
				</div>
			</a>
		</h1>
		<button class="navbar-toggler" type="button"
		        data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
		        aria-controls="navbarSupportedContent" aria-expanded="false"
		        aria-label="<?= Text::_('PANOPTICON_APP_LBL_TOGGLE_NAVIGATION') ?>">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="navbarSupportedContent">
			<ul class="navbar-nav ms-auto mb-2 mb-lg-0">
				<?php if ($this->getMenu()->isEnabled('main') && $user->getId()): ?>
				<?= TemplateHelper::getRenderedMenuItem($this->getMenu()->getMenuItems('main')) ?>
				<?php endif ?>

				<?php if ($user->getId()): ?>
					<a href="<?= $this->getContainer()->router->route('index.php?view=login&task=logout') ?>"
					   class="nav-link"
					>
						<?= Text::_('PANOPTICON_APP_LBL_LOGOUT') ?>
					</a>
				<?php endif; ?>
			</ul>
		</div>
	</nav>
	<?php endif ?>
	<?php // Toolbar / page title ?>
	<?php if (!empty($this->getToolbar()->getTitle()) || count($this->getToolbar()->getButtons())): ?>
	<section class="navbar bg-dark" id="toolbar" data-bs-theme="dark" aria-label="<?= Text::_('PANOPTICON_APP_LBL_TOOLBAR') ?>">
		<div class="ms-2 me-auto">
			<?= TemplateHelper::getRenderedToolbarButtons() ?>
		</div>
		<h2 class="navbar-text ps-2 fs-5 py-0 my-0 me-2">
			<?= $this->getToolbar()->getTitle() ?>
		</h2>
	</section>
	<?php endif ?>
	<?php // Messages ?>
	<?php if ($messages = TemplateHelper::getRenderedMessages()): ?>
	<section aria-label="<?= Text::_('PANOPTICON_APP_LBL_MESSAGES') ?>">
		<?= $messages ?>
	</section>
	<?php endif ?>
</header>

<?php // Main Content ?>
<main class="container-xl">
	<?= $this->getBuffer() ?>
</main>

<?php if (!$isBareDisplay): ?>
<footer class="container-xl bg-dark text-light py-2 text-muted small" data-bs-theme="dark">
	<p class="m-0">
		<?= Text::_('PANOPTICON_APP_TITLE') ?> <?= AKEEBA_PANOPTICON_VERSION ?>
		&bull;
		<?= Text::sprintf('PANOPTICON_APP_LBL_COPYRIGHT', date('Y')) ?>
		<br/>
		<?= Text::sprintf('PANOPTICON_APP_LBL_LICENSE', Text::_('PANOPTICON_APP_TITLE')) ?>
	</p>
	<?php if (defined('AKEEBADEBUG')): ?>
		<p class="m-0 text-light">
		Page creation <?= sprintf('%0.3f', $this->getApplication()->getTimeElapsed()) ?> sec
		&bull;
		Peak memory usage <?= sprintf('%0.1f', memory_get_peak_usage() / 1048576) ?> MiB
	</p>
	<?php endif; ?>
</footer>
<?php endif ?>

</body>
</html>