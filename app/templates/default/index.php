<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBA') || die;

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
	\Akeeba\Panopticon\Helper\DarkModeEnum::DARK => 'dark',
	\Akeeba\Panopticon\Helper\DarkModeEnum::LIGHT => 'light',
	default => ''
};
$versionTag = Version::create(AKEEBA_PANOPTICON_VERSION)->tagType();

TemplateHelper::applyFontSize();
TemplateHelper::applyDarkModeJavaScript();

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

<div class="container-xl">
	<?php // Top header ?>
	<nav class="navbar navbar-expand-lg bg-body-tertiary" id="topNavbar">
		<h1>
			<a class="navbar-brand ps-3" href="<?= Uri::base() ?>">
				<?= file_get_contents(APATH_MEDIA . '/images/logo_colour.svg') ?>
				<?= Text::_('PANOPTICON_APP_TITLE_SHORT') ?>

				<?php if (in_array($versionTag, [Version::TAG_TYPE_ALPHA, Version::TAG_TYPE_BETA, Version::TAG_TYPE_RELEASE_CANDIDATE, Version::TAG_TYPE_DEV])): ?>
					<sup>
						<span class="badge bg-danger-subtle"><?= ucfirst($versionTag) ?></span>
					</sup>
				<?php endif ?>
			</a>
		</h1>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="<?= Text::_('PANOPTICON_APP_LBL_TOGGLE_NAVIGATION') ?>">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="navbarSupportedContent">
			<!-- TODO Menu -->
		</div>
	</nav>
</div>

<!-- TODO Toolbar -->

<div class="container-xl">
	<!-- TODO Main Content -->
</div>

<!-- TODO Footer -->

</body>
</html>