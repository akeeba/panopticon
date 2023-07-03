<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Overrides\Html $this
 */

?>
@repeatable('renderFile', $fileContents)
    <?php
        $hl     = new \Highlight\Highlighter();
        $result = $hl->highlight("php", $fileContents);
        $lines  = \HighlightUtilities\splitCodeIntoArray($result->value);
    ?>
<pre class="codeLines">@foreach($lines as $line)<code>{{ $line }}</code>
@endforeach</pre>
@endrepeatable

<div class="d-flex flex-column mb-4">
    <div class="d-flex flex-column flex-md-row align-items-center">
        <div class="flex-grow-1 d-flex flex-column">
            <h4 class="m-0">
                {{{ trim($this->item->overridePathRelative, '/\\') }}}
            </h4>
            <div class="text-muted">
                {{{ trim($this->item->corePathRelative, '/\\') }}}
            </div>
        </div>
        <div>
            <div class="text-primary fs-bold">
                {{{ $this->item->template }}}
            </div>
            <div>
                <span class="badge {{ $this->item->client == 0 ? 'bg-primary' : 'bg-secondary' }}">
                    @if($this->item->client == 0)
                        @lang('PANOPTICON_OVERRIDES_LBL_FRONTEND')
                    @else
                        @lang('PANOPTICON_OVERRIDES_LBL_BACKEND')
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs" id="overrideTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="diff-tab"
                data-bs-toggle="tab" data-bs-target="#diff-tab-pane"
                type="button" role="tab"
                aria-controls="diff-tab-pane" aria-selected="true">
            @lang('PANOPTICON_OVERRIDES_LBL_DIFF')
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="core-tab"
                data-bs-toggle="tab" data-bs-target="#core-tab-pane"
                type="button" role="tab"
                aria-controls="core-tab-pane" aria-selected="false">
            @lang('PANOPTICON_OVERRIDES_LBL_CORE')
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="override-tab"
                data-bs-toggle="tab" data-bs-target="#override-tab-pane"
                type="button" role="tab"
                aria-controls="override-tab-pane" aria-selected="false">
            @lang('PANOPTICON_OVERRIDES_LBL_OVERRIDE')
        </button>
    </li>
</ul>
<div class="tab-content" id="overrideTabContent">
    <div class="tab-pane show active" id="diff-tab-pane" role="tabpanel"
         aria-labelledby="diff-tab" tabindex="0">
        {{ $this->item->diff }}
    </div>

    <div class="tab-pane" id="core-tab-pane" role="tabpanel"
         aria-labelledby="core-tab" tabindex="0">
        @yieldRepeatable('renderFile', $this->item->coreSource)
    </div>

    <div class="tab-pane" id="override-tab-pane" role="tabpanel"
         aria-labelledby="override-tab" tabindex="0">
        @yieldRepeatable('renderFile', $this->item->overrideSource)
    </div>
</div>