<?php
/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <div class="card-body">
        <h3 class="card-title h5">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_COREUPDATES')</h3>

        {{--tasks_coreupdate_install--}}
        <div class="row mb-3">
            <label for="tasks_coreupdate_install" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_COREUPDATE_INSTALL')
            </label>
            <div class="col-sm-9">
                {{ \Awf\Html\Select::genericList(
                    data: [
                        'none' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE',
                        'email' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL',
                        'patch' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH',
                        'minor' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR',
                        'major' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR',
                    ],
                    name: 'options[tasks_coreupdate_install]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('tasks_coreupdate_install', 'patch'),
                    idTag: 'tasks_coreupdate_install',
                    translate: true
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_COREUPDATE_INSTALL_HELP')
                </div>
            </div>
        </div>

    </div>
</div>