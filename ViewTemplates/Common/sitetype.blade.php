<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;

/**
 * @var  \Akeeba\Panopticon\Model\Site $site
 */
?>
<span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_CMSTYPE_LABEL')</span>

@if($site->cmsType() === CMSType::JOOMLA)
    <span class="fab fa-joomla" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_CMSTYPE_OPT_JOOMLA')</span>
@elseif($site->cmsType() === CMSType::WORDPRESS)
    <span class="fab fa-wordpress" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_CMSTYPE_OPT_WORDPRESS')</span>
@else
    <span class="fa fa-globe" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_CMSTYPE_OPT_OTHER')</span>
@endif
