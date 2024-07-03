<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$router = $this->getContainer()->router;

?>

<form action="<?php echo $router->route('index.php?view=sysconfig') ?>" method="post" id="adminForm">

    <ul class="nav nav-tabs" id="sysconfigTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabSystem"
                    class="nav-link active" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabSystemContent" aria-controls="sysconfigTabSystemContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_SYSTEM')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabEmail"
                    class="nav-link" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabEmailContent" aria-controls="sysconfigTabEmailContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_EMAIL')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabDisplay"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabDisplayContent" aria-controls="sysconfigTabDisplayContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_DISPLAY')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabAutomation"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabAutomationContent" aria-controls="sysconfigTabAutomationContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_AUTOMATION')
            </button>
        </li>
    </ul>
    <div class="tab-content container py-3" id="sysconfigTabContent" tabindex="-1">
        <div class="tab-pane show active"
             id="sysconfigTabSystemContent" role="tabpanel" aria-labelledby="sysconfigTabSystem" tabindex="-1"
        >
            <div class="d-flex flex-column gap-3">
                @include('Sysconfig/default_system')
                @include('Sysconfig/default_loginfail')
                @include('Sysconfig/default_passwordsec')
                @include('Sysconfig/default_caching')
                @include('Sysconfig/default_logging')
                @include('Sysconfig/default_proxy')
                @include('Sysconfig/default_dbtools')
                @include('Sysconfig/default_database')
            </div>
        </div>
        <div class="tab-pane"
             id="sysconfigTabEmailContent" role="tabpanel" aria-labelledby="sysconfigTabEmail" tabindex="-1"
        >
            <div class="d-flex flex-column gap-3">
                @include('Sysconfig/default_email')
            </div>
        </div>
        <div class="tab-pane"
             id="sysconfigTabDisplayContent" role="tabpanel" aria-labelledby="sysconfigTabDisplay" tabindex="-1"
        >
            <div class="d-flex flex-column gap-3">
                @include('Sysconfig/default_display')
            </div>
        </div>
        <div class="tab-pane"
             id="sysconfigTabAutomationContent" role="tabpanel" aria-labelledby="sysconfigTabAutomation" tabindex="-1"
        >
            <div class="d-flex flex-column gap-3">
                @include('Sysconfig/default_automation')
                @include('Sysconfig/default_uptime')
                @include('Sysconfig/default_sites')
                @include('Sysconfig/default_coreupdates')
                @include('Sysconfig/default_extupdates')
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="@token" value="1">
</form>
