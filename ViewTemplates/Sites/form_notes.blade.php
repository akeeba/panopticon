<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */

$theme    = $this->getContainer()->appConfig->get('theme', 'theme') ?: 'theme';
$filePath = \Awf\Utils\Template::parsePath('media://css/' . $theme . '.min.css', true, $this->getContainer()->application);
$css      = @file_get_contents($filePath) ?: '';
?>

<div class="alert alert-warning mb-2">
    <span class="fa fa-fw fa-exclamation-triangle" aria-hidden="true"></span>
    @lang('PANOPTICON_SITE_LBL_NOTES_HELP_NO_PRIVILEGED')
</div>

<div class="row mb-3">
    <label for="html" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITE_LBL_NOTES_HEAD')
        <div class="form-text mt-4">
            @lang('PANOPTICON_SITE_LBL_NOTES_HELP')
        </div>
    </label>
    <div class="col-sm-9">
        {{ \Akeeba\Panopticon\Library\Editor\TinyMCE::editor(
            'notes',
			$this->item->notes,
            [
                'id' => 'notes',
                'relative_urls' => true,
            ]
        ) }}
    </div>
</div>