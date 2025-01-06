<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_COREUPDATES')</h3>
    <div class="card-body">
        {{--tasks_coreupdate_install--}}
        <div class="row mb-3">
            <label for="tasks_coreupdate_install" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_COREUPDATE_INSTALL')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
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