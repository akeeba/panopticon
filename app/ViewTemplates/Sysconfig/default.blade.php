<?php
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
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabSites"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabSitesContent" aria-controls="sysconfigTabSitesContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_SITES')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabCaching"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabCachingContent" aria-controls="sysconfigTabCachingContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_CACHING')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabLogging"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabLoggingContent" aria-controls="sysconfigTabLoggingContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_LOGGING')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="sysconfigTabDatabase"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#sysconfigTabDatabaseContent" aria-controls="sysconfigTabDatabaseContent">
                @lang('PANOPTICON_SYSCONFIG_LBL_TAB_DATABASE')
            </button>
        </li>
    </ul>
    <div class="tab-content py-3" id="sysconfigTabContent">
        <div class="tab-pane show active"
             id="sysconfigTabSystemContent" role="tabpanel" aria-labelledby="sysconfigTabSystem" tabindex="0"
        >
            @include('Sysconfig/default_system')
        </div>
        <div class="tab-pane"
             id="sysconfigTabDisplayContent" role="tabpanel" aria-labelledby="sysconfigTabDisplay" tabindex="0"
        >
            {{--include('Sysconfig/default_display')--}}
        </div>
        <div class="tab-pane"
             id="sysconfigTabAutomationContent" role="tabpanel" aria-labelledby="sysconfigTabAutomation" tabindex="0"
        >
            {{--include('Sysconfig/default_automation')--}}
        </div>
        <div class="tab-pane"
             id="sysconfigTabSitesContent" role="tabpanel" aria-labelledby="sysconfigTabSites" tabindex="0"
        >
            {{--include('Sysconfig/default_sites')--}}
        </div>
        <div class="tab-pane"
             id="sysconfigTabCachingContent" role="tabpanel" aria-labelledby="sysconfigTabCaching" tabindex="0"
        >
            {{--include('Sysconfig/default_caching')--}}
        </div>
        <div class="tab-pane"
             id="sysconfigTabLoggingContent" role="tabpanel" aria-labelledby="sysconfigTabLogging" tabindex="0"
        >
            {{--include('Sysconfig/default_logging')--}}
        </div>
        <div class="tab-pane"
             id="sysconfigTabDatabaseContent" role="tabpanel" aria-labelledby="sysconfigTabDatabase" tabindex="0"
        >
            {{--include('Sysconfig/default_database')--}}
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="@token" value="1">
</form>
