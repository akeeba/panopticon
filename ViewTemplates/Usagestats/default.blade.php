<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Awf\Uri\Uri;

/**
 * @var \Akeeba\Panopticon\View\Usagestats\Html $this
 */

$token = $this->getContainer()->session->getCsrfToken()->getValue();
?>

<div class="alert alert-info">
    <h3 class="alert-heading">
        <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
        @lang('PANOPTICON_USAGESTATS_LBL_INFO_TITLE')
    </h3>
    <p>
        @lang('PANOPTICON_USAGESTATS_LBL_INFO_BODY')
    </p>
</div>

<h3>
    @lang('PANOPTICON_USAGESTATS_LBL_INFO_COLLECTED_HEAD')
</h3>

@if (!$this->isCollectionEnabled)
    <div class="alert alert-danger">
        <h4 class="alert-heading">
            @lang('PANOPTICON_USAGESTATS_LBL_DISABLED_HEAD')
        </h4>
        <p>
            @lang('PANOPTICON_USAGESTATS_LBL_DISABLED_MESSAGE')
        </p>
    </div>
@elseif (empty($this->data))
    <div class="alert alert-danger">
        <h4 class="alert-heading">
            @lang('PANOPTICON_USAGESTATS_LBL_NOINFO_HEAD')
        </h4>
        <p>
            @lang('PANOPTICON_USAGESTATS_LBL_NOINFO_MESSAGE')
        </p>
    </div>
@else
    <p>
        @lang('PANOPTICON_USAGESTATS_LBL_INFO_EXPLANATION')
    </p>
    <p>
        @sprintf('PANOPTICON_USAGESTATS_LBL_SERVER_DOMAIN', $this->escape(Uri::getInstance($this->serverUrl)->getHost()))
        @if (empty($this->lastCollectionDate))
            @lang('PANOPTICON_USAGESTATS_LBL_LAST_COLLECTION_NEVER')
        @else
            @sprintf(
	            'PANOPTICON_USAGESTATS_LBL_LAST_COLLECTION_DATE',
                $this->getContainer()->html->basic->date($this->lastCollectionDate, $this->getLanguage()->text('DATE_FORMAT_LC7'))
            )
        @endif
    </p>
    <table class="table table-striped">
        <comment class="visually-hidden">
            @lang('PANOPTICON_USAGESTATS_LBL_TABLE_COMMENT')
        </comment>
        <thead>
        <tr>
            <th>
                @lang('PANOPTICON_USAGESTATS_LBL_KEY')
            </th>
            <th>
                @lang('PANOPTICON_USAGESTATS_LBL_VALUE')
            </th>
            <th>
                @lang('PANOPTICON_USAGESTATS_LBL_WHATIS')
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach ($this->data as $k => $v)
            <tr>
                <td>
                    <code>{{{$k}}}</code>
                </td>
                <td>
                    <code>{{{$v}}}</code>
                </td>
                <td>
                    @if($this->getLanguage()->hasKey('PANOPTICON_USAGESTATS_LBL_DATAINFO_' . $k))
                        @lang('PANOPTICON_USAGESTATS_LBL_DATAINFO_' . $k)
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="d-flex flex-column flex-md-row align-items-center justify-content-evenly">
    @if ($this->isCollectionEnabled)
        @if(!BootstrapUtilities::hasConfiguration(true))
        <a href="@route('index.php?view=usagestats&task=disable&' . $token . '=1')" role="button"
           class="btn btn-danger">
            <span class="fa fa-fw fa-power-off" aria-hidden="true"></span>
            @lang('PANOPTICON_USAGESTATS_BTN_DISABLE')
        </a>
        @endif
        <a href="@route('index.php?view=usagestats&task=resetsid&' . $token . '=1')" role="button"
           class="btn btn-outline-warning btn-sm">
            <span class="fa fa-fw fa-refresh" aria-hidden="true"></span>
            @lang('PANOPTICON_USAGESTATS_BTN_RESET_SID')
        </a>
    @else
        @if(!BootstrapUtilities::hasConfiguration(true))
        <a href="@route('index.php?view=usagestats&task=enable&' . $token . '=1')" role="button"
           class="btn btn-success">
            <span class="fa fa-fw fa-power-off" aria-hidden="true"></span>
            @lang('PANOPTICON_USAGESTATS_BTN_ENABLE')
        </a>
        @endif
    @endif

    @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
    <a href="@route('index.php?view=usagestats&task=send&' . $token . '=1')" role="button"
       class="btn btn-outline-secondary btn-sm">
        <span class="fa fa-fw fa-paper-plane" aria-hidden="true"></span>
        @lang('PANOPTICON_USAGESTATS_BTN_SEND')
    </a>
    @endif
</div>