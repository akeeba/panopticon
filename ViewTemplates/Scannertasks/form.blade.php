<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Awf\Registry\Registry;

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Scannertasks\Html $this
 * @var \Akeeba\Panopticon\Model\Task            $model
 */
$model   = $this->getModel();
$token   = $this->container->session->getCsrfToken()->getValue();
$params  = is_object($model->params) ? $model->params : new Registry($model->params);
$favIcon = $this->site->getFavicon(asDataUrl: true, onlyIfCached: true);

try
{
	$cronExpression = new \Cron\CronExpression($model->cron_expression ?? '@daily');
	$ceMinutes = $cronExpression->getExpression(0);
	$ceHours = $cronExpression->getExpression(1);
	$ceDom = $cronExpression->getExpression(2);
	$ceMonth = $cronExpression->getExpression(3);
	$ceDow = $cronExpression->getExpression(4);
}
catch (InvalidArgumentException $e)
{
	[$ceMinutes, $ceHours, $ceDom, $ceMonth, $ceDow] = explode(' ', $model->cron_expression);
}
?>

<form action="@route('index.php?view=scannertasks')"
      method="post" name="adminForm" id="adminForm">

    <h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
        <span class="text-muted fw-light fs-4">#{{ (int) $this->site->id }}</span>
        @if($favIcon)
            <img src="{{{ $favIcon }}}"
                style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
                class="mx-1 p-1 border rounded"
                alt="">
        @endif
        <span class="flex-grow-1">{{{ $this->site->name }}}</span>
    </h3>

    {{-- CRON Expression --}}
    <div>
        <div class="alert alert-info">
            <span class="fa fa-info-circle" aria-hidden="true"></span>
            @sprintf(
              'PANOPTICON_SCANNERTASKS_LBL_TIMEZONE_NOTICE',
              'https://en.wikipedia.org/wiki/Cron#Cron_expression',
              (new DateTimeZone($this->container->appConfig->get('timezone', 'UTC')))->getName()
            )
        </div>

        <div class="row mb-3">
            <label for="minutes" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_MINUTES')
            </label>
            <div class="col-sm-9">
                <input name="cron[minutes]" id="minutes"
                       type="text" class="form-control"
                       value="{{{ $ceMinutes }}}" required
                >
                <div class="form-text collapse">
                    @lang('PANOPTICON_SCANNERTASKS_LBL_MINUTES_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="hours" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_HOURS')
            </label>
            <div class="col-sm-9">
                <input name="cron[hours]" id="hours"
                       type="text" class="form-control"
                       value="{{{ $ceHours }}}" required
                >
                <div class="form-text collapse">
                    @lang('PANOPTICON_SCANNERTASKS_LBL_HOURS_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="dom" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_DOM')
            </label>
            <div class="col-sm-9">
                <input name="cron[dom]" id="dom"
                       type="text" class="form-control"
                       value="{{{ $ceDom }}}" required
                >
                <div class="form-text collapse">
                    @lang('PANOPTICON_SCANNERTASKS_LBL_DOM_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="month" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_MONTH')
            </label>
            <div class="col-sm-9">
                <input name="cron[month]" id="month"
                       type="text" class="form-control"
                       value="{{{ $ceMonth }}}" required
                >
                <div class="form-text collapse">
                    @lang('PANOPTICON_SCANNERTASKS_LBL_MONTH_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="dow" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_DOW')
            </label>
            <div class="col-sm-9">
                <input name="cron[dow]" id="dow"
                       type="text" class="form-control"
                       value="{{{ $ceDow }}}" required
                >
                <div class="form-text collapse">
                    @lang('PANOPTICON_SCANNERTASKS_LBL_DOW_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="run_once" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SCANNERTASKS_LBL_FIELD_RUN_ONCE')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    [
						'' => 'PANOPTICON_SCANNERTASKS_LBL_FIELD_RUN_ONCE_NONE',
						'disable' => 'PANOPTICON_SCANNERTASKS_LBL_FIELD_RUN_ONCE_DISABLE',
						'delete' => 'PANOPTICON_SCANNERTASKS_LBL_FIELD_RUN_ONCE_DELETE',
                    ],
                    'params[run_once]',
                    [
                        'class' => 'form-select',
                    ],
                    selected: $params->get('run_once', ''),
                    idTag: 'run_once',
                    translate: true
                ) }}
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" value="1"
                           name="enabled" id="enabled"
                            {{ $model->enabled ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="enabled">
                        @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
                    </label>
                </div>
            </div>
        </div>

    </div>

    <input type="hidden" name="id" value="{{{ $model->id ?? 0 }}}">
    <input type="hidden" name="site_id" value="{{{ $this->site->getId() ?? 0 }}}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">

</form>