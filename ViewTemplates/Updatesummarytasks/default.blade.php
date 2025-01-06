<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

/**
 * @var \Akeeba\Panopticon\View\Updatesummarytasks\Html $this
 * @var \Akeeba\Panopticon\Model\Task            $model
 */
$model    = $this->getModel();
$token    = $this->container->session->getCsrfToken()->getValue();
$i        = 1;
$favIcon  = $this->site->getFavicon(asDataUrl: true, onlyIfCached: true);

?>

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
<form action="@route('index.php?view=updatesummarytasks')" method="post" name="adminForm" id="adminForm">
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="d-flex flex-row justify-content-center">
  	      <div class="input-group pnp-mw-50">
                <input type="search" class="form-control form-control-lg" id="search"
                       placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                       name="name" value="{{{ $model->getState('name', '') }}}">
                <label for="search" class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
                <button type="submit"
                        class="btn btn-primary">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
                </button>
            </div>
        </div>
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <div>
                <label class="visually-hidden" for="enabled">@lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')</label>
                {{ $this->container->html->select->genericList( [
	                '' => 'PANOPTICON_TASKS_LBL_SELECT_ENABLED',
	                '0' => 'PANOPTICON_LBL_UNPUBLISHED',
	                '1' => 'PANOPTICON_LBL_PUBLISHED',
                ], 'enabled', [
					'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                ], selected: $model->getState('enabled'),
                idTag: 'enabled',
                translate: true) }}
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <span class="fa fa-info-circle" aria-hidden="true"></span>
        @sprintf(
          'PANOPTICON_UPDATESUMMARYTASKS_LBL_TIMEZONE_NOTICE',
		  'https://en.wikipedia.org/wiki/Cron#Cron_expression',
          (new DateTimeZone($this->container->appConfig->get('timezone', 'UTC')))->getName()
        )
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_SITES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                @lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_SCHEDULE')
            </th>
            <th class="pnp-w-5">
                @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
            </th>
            <th class="pnp-w-5">
                @lang('PANOPTICON_TASKS_LBL_FIELD_STATUS')
            </th>
            <th>
                @lang('PANOPTICON_TASKS_LBL_FIELD_TIMES')
            </th>
            <th class="pnp-w-5">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_NUM', 'id', $this->lists->order_Dir, $this->lists->order, 'browse', attribs: [
                    'aria-label' => $this->getLanguage()->text('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')
                ]) }}
            </th>
        </tr>
        </thead>
        <tbody>
		<?php /** @var \Akeeba\Panopticon\Model\Task $task */ ?>
        @foreach($this->items as $task)
            <?php $params = is_object($task->params) ? $task->params : new Registry($task->params); ?>
            <tr>
                {{-- Checkbox --}}
                <td>
                    {{ $this->getContainer()->html->grid->id(++$i, $task->id) }}
                </td>
                {{-- Schedule --}}
                <td>
                    <div>
                        <a href="@route(sprintf('index.php?view=updatesummarytasks&task=edit&site_id=%d&id=%d', $this->site->id, $task->id))">
                            <code>{{{ $task->cron_expression  }}}</code>
                        </a>
                    </div>
                    <div class="mt-1 pt-1 border-top d-flex flex-row gap-0">
                        @if($params->get('run_once') == 'disable')
                            <span class="fa fa-fw fa-stack fa-circle-stop text-warning" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_DISABLE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_DISABLE')</span>
                        @elseif($params->get('run_once') == 'delete')
                            <span class="fa fa-fw fa-stack fa-land-mine-on text-danger" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_DELETE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_DELETE')</span>
                        @else
                            <span class="fa fa-fw fa-stack fa-repeat text-success-emphasis" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_NONE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_BACKUPTASKS_LBL_FIELD_RUN_ONCE_NONE')</span>
                        @endif

                        @if($params->get('core_updates', true))
                            @if($this->site->cmsType() === CMSType::WORDPRESS)
                                <span class="fab fa-fw fa-stack fa-wordpress" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_CORE_UPDATES')"></span>
                            @else
                                <span class="fab fa-fw fa-stack fa-joomla" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_CORE_UPDATES')"></span>
                            @endif
                            <span class="visually-hidden">@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_CORE_UPDATES')</span>
                        @endif

                        @if($params->get('extension_updates', true))
                            <span class="fa fa-fw fa-stack fa-cubes" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_EXTENSION_UPDATES')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_EXTENSION_UPDATES')</span>
                        @endif

                        @if($params->get('prevent_duplicates', true))
                            <span class="fa-stack fa-1x" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_PREVENT_DUPLICATES')">
                                <span class="fa fa-copy fa-stack-1x text-secondary" aria-hidden="true"></span>
                                <span class="fa fa-slash fa-stack-1x text-danger" aria-hidden="true"></span>
                            </span>
                                <span class="visually-hidden">@lang('PANOPTICON_UPDATESUMMARYTASKS_LBL_PREVENT_DUPLICATES')</span>
                        @endif

                    </div>
                </td>
                {{-- Enabled --}}
                <td>
                    @if ($task->enabled)
                        <a class="text-decoration-none text-success"
                           href="@route(sprintf('index.php?view=updatesummarytasks&site_id=%d&task=unpublish&id=%d&%s=1', $this->site->id, $task->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_PUBLISHED')"
                        >
                            <span class="fa fa-circle-check" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_LBL_PUBLISHED')</span>
                        </a>
                    @else
                        <a class="text-decoration-none text-danger"
                           href="@route(sprintf('index.php?view=updatesummarytasks&task=publish&site_id=%d&id=%d&%s=1', $this->site->id, $task->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_UNPUBLISHED')"
                        >
                            <span class="fa fa-circle-xmark" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_LBL_PUBLISHED')</span>
                        </a>
                    @endif
                </td>
                {{-- Status --}}
                <td>
						<?php $status = ($task->last_exit_code instanceof Status) ? $task->last_exit_code : Status::tryFrom($task->last_exit_code) ?>
                    @if ($status->value == Status::OK->value)
                        <span class="fa fa-check-circle text-success" aria-hidden="true"
                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                              data-bs-title="{{{ str_replace('"', '“', $status->forHumans()) }}}"
                        ></span>
                        <span class="visually-hidden">{{{ $status->forHumans() }}}</span>
                    @elseif ($status->value == Status::INITIAL_SCHEDULE->value)
                        <span class="fa fa-clock text-info" aria-hidden="true"
                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                              data-bs-title="{{{ str_replace('"', '“', $status->forHumans()) }}}"></span>
                        <span class="visually-hidden">{{{ $status->forHumans() }}}</span>
                    @elseif ($status->value == Status::WILL_RESUME->value || $status->value == Status::RUNNING->value)
                        <span class="fa fa-play text-warning" aria-hidden="true"
                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                              data-bs-title="{{{ str_replace('"', '“', $status->forHumans()) }}}"></span>
                        <span class="visually-hidden">{{{ $status->forHumans() }}}</span>
                    @elseif ($status->value == Status::EXCEPTION->value)
                        <a data-bs-toggle="modal" data-bs-target="#exceptionModal_{{ (int) $task->id }}">
                            <span class="fa fa-exclamation-circle text-danger" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="{{{ str_replace('"', '“', $status->forHumans()) }}}"
                            ></span>
                            <span class="visually-hidden">{{{ $status->forHumans() }}}</span>
                        </a>

                        <div class="modal fade" id="exceptionModal_{{ (int) $task->id }}" tabindex="-1"
                             aria-labelledby="exceptionModal_{{ (int) $task->id }}_Label" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h1 class="modal-title fs-5"
                                            id="exceptionModal_{{ (int) $task->id }}_Label">{{{ $status->forHumans() }}}</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
                                    </div>
                                    <div class="modal-body">
											<?php $storage = $task->storage instanceof Registry ? $task->storage : new Registry($task->storage); ?>
                                        <p>
                                            {{{ $storage->get('error') }}}
                                        </p>
                                        <pre>{{{ $storage->get('trace') }}}</pre>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    @else
                        <span class="fa fa-exclamation-triangle text-danger" aria-hidden="true"
                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                              data-bs-title="{{{ str_replace('"', '“', $status->forHumans()) }}}"></span>
                        <span class="visually-hidden">{{{ $status->forHumans() }}}</span>
                    @endif

                </td>
                {{-- Last / Next Run --}}
                <td>
                    @if ($status->value !== Status::WILL_RESUME->value)
                        <div class="fw-semibold">
                            <span class="fa fa-clock" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_TASKS_LBL_LAST_RUN')"
                            ></span>
                            <span class="visually-hidden">@lang('PANOPTICON_TASKS_LBL_LAST_RUN')</span>
                            {{ $task->last_execution ? $this->getContainer()->html->basic->date($task->last_execution, $this->getLanguage()->text('DATE_FORMAT_LC6') . ' T') : '&mdash;' }}
                        </div>
                    @endif
                    @if ($task->enabled)
                        <div class="text-info mt-1">
                            <span class="fa fa-clock-rotate-left" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_TASKS_LBL_NEXT_RUN')"
                            ></span>
                            <span class="visually-hidden">@lang('PANOPTICON_TASKS_LBL_NEXT_RUN')</span>
                            {{ $task->next_execution ? $this->getContainer()->html->basic->date($task->next_execution, $this->getLanguage()->text('DATE_FORMAT_LC6') . ' T') : '&mdash;' }}
                        </div>
                    @endif
                    @if ($duration = $task->getDuration())
                        <div class="text-body-tertiary mt-1">
                            <span class="fa fa-stopwatch" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_TASKS_LBL_DURATION')"
                            ></span>
                            <span class="visually-hidden">@lang('PANOPTICON_TASKS_LBL_DURATION')</span>
                            {{{ $duration }}}
                        </div>
                    @endif
                </td>
                {{-- ID --}}
                <td class="font-monospace text-end">
                    {{ (int) $task->id }}
                </td>
            </tr>
        @endforeach
        @if (!$this->items?->count())
            <tr>
                <td colspan="20" class="text-center text-body-tertiary">
                    @lang('AWF_PAGINATION_LBL_NO_RESULTS')
                </td>
            </tr>
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td colspan="20" class="center">
                {{ $this->pagination->getListFooter(['class' => 'form-select akeebaGridViewAutoSubmitOnChange']) }}
            </td>
        </tr>
        </tfoot>
    </table>


    <input type="hidden" name="boxchecked" id="boxchecked" value="0">
    <input type="hidden" name="task" id="task" value="browse">
    <input type="hidden" name="filter_order" id="filter_order" value="{{{ $this->lists->order }}}">
    <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{{ $this->lists->order_Dir }}}">
    <input type="hidden" name="site_id" id="site_id" value="{{{ (int) $this->site->id }}}">
    <input type="hidden" name="token" value="@token()">
</form>
