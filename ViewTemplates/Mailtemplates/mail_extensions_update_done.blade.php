<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * @var \Akeeba\Panopticon\View\Mailtemplates\Html $this
 * @var array                                      $updateStatus
 * @var \Akeeba\Panopticon\Model\Site              $site
 */

defined('AKEEBA') || die;

$updateStatus = array_map(fn($x) => (array)$x, $updateStatus);

$hasFailed = array_reduce(
	$updateStatus,
	fn(bool $carry, array $item) => $carry || $item['status'] !== 'success',
	false
);

$hasSuccess = array_reduce(
	$updateStatus,
	fn(bool $carry, array $item) => $carry || $item['status'] === 'success',
	false
);

$moreThanOne = count($updateStatus) > 1;

?>
        <!-- Main-Topic -->
<div class="akemail-main-topic">
    <p>
        @if ($hasFailed && !$hasSuccess)
            @if($moreThanOne)
                The extension updates for {{{ $site->name }}} have failed.
            @else
                The extension update for {{{ $site->name }}} has failed.
            @endif
        @elseif ($hasFailed)
            Some extension updates for {{{ $site->name }}} have failed.
        @else
            @if($moreThanOne)
                The extension updates for {{{ $site->name }}} were successful.
            @else
                The extension update for {{{ $site->name }}} was successful.
            @endif
        @endif
    </p>
</div>
<!-- Message -->
<div class="akemail-message">
    @if($hasSuccess)
        <p>The following extensions have been updated successfully:</p>
        @foreach($updateStatus as $info)
            <?php if ($info['status'] !== 'success') continue ?>
            <p>
                <strong>@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{{ $info['name'] }}}”</strong>.
                @if (!empty($info['messages']))
                    Update messages:
                    <br />
                    {{{ implode("<br/>\n", array_map('htmlentities', $info['messages']) ) }}}
                @endif
            </p>
        @endforeach
    @endif
    @if($hasFailed)
        <p>The following extensions have failed to update:</p>
        @foreach($updateStatus as $info)
            <?php if ($info['status'] === 'success') continue ?>
            <p>
                <strong>@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{{ $info['name'] }}}”</strong>.
                @if ($info['status'] === 'exception')
                    An application or network error occurred.
                @elseif ($info['status'] === 'invalid_json')
                    The site's server returned a response we do not understand.
                @elseif ($info['status'] === 'error')
                    Your Joomla! site encountered an error trying to install the updated version.
                @endif
                @if (!empty($info['messages']))
                    Update messages:
                    <br />
                    {{{ implode("<br/>\n", array_map('htmlentities', $info['messages']) ) }}}
                @endif
            </p>
        @endforeach
    @endif
</div>