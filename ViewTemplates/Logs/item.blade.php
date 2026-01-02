<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Awf\Text\Text;

/**
 * @var \Akeeba\Panopticon\View\Logs\Html $this
 */

$timeFormat = 'j M y H:i:s T';
$hasFacility = array_reduce($this->logLines, fn(bool $carry, object $item) => $carry || !empty($item->facility), false);
?>

<h3 class="d-flex flex-column flex-md-row align-items-center mt-md-3 mb-md-4">
    <span class="flex-md-grow-1">
        {{{ basename($this->filePath) }}}
    </span>
    <span class="fs-5 font-monospace text-success-emphasis">{{{ $this->filesize(basename($this->filePath)) }}}</span>
</h3>

<div class="my-2 mb-4 border rounded-1 p-2 bg-body-tertiary">
    <div class="my-2 d-flex flex-row flex-wrap justify-content-evenly align-items-center">
        <div>
            <div class="input-group">
                <label for="size" class="input-group-text">
                    @lang('PANOPTICON_LOGS_LBL_MAX_INPUT_SIZE')
                </label>
                {{ $this->container->html->select->genericList([
                    '10240' => '10 KiB',
                    '65536' => '64 KiB',
                    '131072' => '128 KiB',
                    '262144' => '256 KiB',
                    '524288' => '512 KiB',
                    '1048576' => '1 MiB',
                    '2097152' => '2 MiB',
                    '5242880' => '5 MiB',
                    '10485760' => '10 MiB',
                ], 'size', [
                    'class' => 'form-select',
                ], selected: $this->getModel()->getState('size', 131072),
                    idTag: 'size',
                    translate: false) }}
            </div>
        </div>

        <div>
            <div class="input-group">
                <label for="lines" class="input-group-text">
                    @lang('PANOPTICON_LOGS_LBL_MAX_LINES')
                </label>
                {{ $this->container->html->select->genericList([
                    '10' => '10',
                    '20' => '20',
                    '50' => '50',
                    '100' => '100',
                    '250' => '250',
                    '500' => '500',
                    '1000' => '1000',
                    '2000' => '2000',
                    '5000' => '5000',
                    '10000' => '10000',
                ], 'lines', [
                    'class' => 'form-select',
                ], selected: $this->getModel()->getState('lines', 500),
                    idTag: 'lines',
                    translate: false) }}
            </div>
        </div>

        <div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       value="" id="autoRefresh">
                <label class="form-check-label" for="autoRefresh">
                    @lang('PANOPTICON_LOGS_LBL_AUTOREFRESH')
                </label>
            </div>
        </div>

        <div>
            <button type="button" id="reloadLog"
                    class="btn btn-outline-primary">
                <span class="fa fa-fw fa-retweet" aria-hidden="true"></span>
                @lang('PANOPTICON_LOGS_LBL_RELOAD')
            </button>
        </div>

    </div>
    <div id="autoRefreshContainer" class="d-none">
        <div class="progress" role="progressbar" id="autoRefreshProgress"
             aria-label="@lang('PANOPTICON_LOGS_LBL_SR_AUTOREFRESH_TIME')"
             aria-valuenow="2" aria-valuemin="0" aria-valuemax="10"
             style="max-width: max(15em, 80%); margin: 0 auto"
        >
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="autoRefreshBar" style="width: 20%"></div>
        </div>
    </div>
</div>

<div id="logTableContainer">
@include('Logs/item_table')
</div>