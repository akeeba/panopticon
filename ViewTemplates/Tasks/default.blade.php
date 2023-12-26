<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Task\Status;
use Awf\Html\Select;
use Awf\Registry\Registry;
use Awf\Html\Html as HtmlHelper;
use Awf\Text\Text;

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Tasks\Html $this
 * @var \Akeeba\Panopticon\Model\Task      $model
 */
$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();
$i     = 1;
?>

<form action="@route('index.php?view=tasks')" method="post" name="adminForm" id="adminForm">
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="d-flex flex-row justify-content-center">
            <div class="input-group" style="max-width: max(50%, 25em)">
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
                <label class="visually-hidden" for="site_id">@lang('PANOPTICON_TASKS_LBL_FIELD_SITE_ID')</label>
                {{
                   $this->container->helper->setup->siteSelect(
	                   selected: $model->getState('site_id') ?? '',
	                   name: 'site_id',
	                   attribs: [
						   'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                       ]
	               )
                }}
            </div>
            <div>
                <label class="visually-hidden" for="type">@lang('PANOPTICON_TASKS_LBL_FIELD_TYPE')</label>
                {{ $this->container->html->select->genericList(
	                array_merge(['' => $this->getLanguage()->text('PANOPTICON_TASKS_LBL_SELECT_TYPE')], $this->getTaskTypeOptions()),
	                'type',
	                [
						'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                    ],
                    selected: $model->getState('type'),
                    idTag: 'type'
                ) }}
            </div>
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

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_SITES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th width="1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                @lang('PANOPTICON_TASKS_LBL_FIELD_SITE_ID')
            </th>
            <th>
                @lang('PANOPTICON_TASKS_LBL_FIELD_TYPE')
            </th>
            <th width="5%">
                @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
            </th>
            <th width="5%">
                @lang('PANOPTICON_TASKS_LBL_FIELD_STATUS')
            </th>
            <th>
                @lang('PANOPTICON_TASKS_LBL_FIELD_TIMES')
            </th>
            <th width="5%">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_NUM', 'id', $this->lists->order_Dir, $this->lists->order, 'browse', attribs: [
                    'aria-label' => $this->getLanguage()->text('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')
                ]) }}
            </th>
        </tr>
        </thead>
        <tbody>
		<?php /** @var \Akeeba\Panopticon\Model\Task $task */ ?>
        @foreach($this->items as $task)
            <tr>
                {{-- Checkbox --}}
                <td>
                    {{ $this->getContainer()->html->grid->id(++$i, $task->id) }}
                </td>
                {{-- Site --}}
                <td>
                    @if ($task->site_id == 0)
                        <span class="fa fa-robot text-muted" aria-hidden="true"></span>
                        <span class="fw-medium">@lang('PANOPTICON_TASKS_LBL_SYSTEM')</span>
                    @else
                        <span class="fa fa-globe text-body-tertiary" aria-hidden="true"></span>
                        <span class="fw-medium">{{{ $this->siteNames[$task->site_id] }}}</span>
                        <br>
                        <span class="text-secondary">#{{ (int) $task->site_id }}</span>
                    @endif
                </td>
                {{-- Task Type --}}
                <td>
                    <code class="text-muted">{{{ $task->type }}}</code>
                    <div class="small text-muted">
                        <?php
                            try {
                                $taskDescription = $this->container->taskRegistry->get($task->type)->getDescription();
                            } catch (\Akeeba\Panopticon\Exception\InvalidTaskType) {
	                            $taskDescription = null;
                            }
                        ?>
                        {{{ $taskDescription ?? "❓❓❓" }}}
                    </div>
                </td>
                {{-- Enabled --}}
                <td>
                    @if ($task->enabled)
                        @unless($task->site_id <= 0)
                            <a class="text-decoration-none text-success"
                               href="@route(sprintf('index.php?view=tasks&task=unpublish&id=%d&%s=1', $task->id, $token))"
                               data-bs-toggle="tooltip" data-bs-placement="bottom"
                               data-bs-title="@lang('PANOPTICON_LBL_PUBLISHED')"
                            >
                                <span class="fa fa-circle-check" aria-hidden="true"></span>
                                <span class="visually-hidden">@sprintf('PANOPTICON_LBL_PUBLISHED_SR', $task->id)</span>
                            </a>
                        @else
                            <span class="fa fa-circle-check" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="@lang('PANOPTICON_LBL_PUBLISHED')"></span>
                            <span class="visually-hidden">@sprintf('PANOPTICON_LBL_UNPUBLISHED_SR', $task->id)</span>
                        @endunless
                    @else
                        <a class="text-decoration-none text-danger"
                           href="@route(sprintf('index.php?view=tasks&task=publish&id=%d&%s=1', $task->id, $token))"
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
    <input type="hidden" name="token" value="@token()">
</form>
