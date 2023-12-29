<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupInvalidBody;
use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupNoEndpoint;
use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupNotInstalled;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupIsNotPro;
use GuzzleHttp\Exception\GuzzleException;

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$connectionError = $this->akeebaBackupConnectionError;
$isOnlyAWarning  = $connectionError instanceof AkeebaBackupNotInstalled
                   || $connectionError instanceof AkeebaBackupIsNotPro;
$isJoomla3       = str_ends_with(rtrim($this->item->url, '/'), '/panopticon_api');
?>

@if($connectionError instanceof AkeebaBackupNotInstalled)
    {{-- Akeeba Backup is not installed (not Core, not Pro; nothing) --}}
    <div class="alert alert-info">
        <h3 class="alert-heading h5">
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_HEAD')
        </h3>
        <p>
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_BODY')
        </p>
        <p class="small text-muted">
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_NOTE_FOR_AFTERWARDS')
        </p>
    </div>
@elseif($connectionError instanceof AkeebaBackupIsNotPro)
    {{-- You have Akeeba Backup Core, not Akeeba Backup Professional --}}
    <div class="alert alert-info">
        <h3 class="alert-heading h5">
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_HEAD')
        </h3>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_BODY')
    </div>
@elseif ($connectionError instanceof GuzzleException)
    {{-- Connection error to the Panopticon API --}}
    <p class="fw-semibold">
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_COMSERROR_HEAD')
    </p>
    <p>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_GUZZLE')
    </p>
@elseif ($connectionError instanceof AkeebaBackupInvalidBody)
    {{-- Invalid response body from the Panopticon API --}}
    <p class="fw-semibold">
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_INVALIDAPI_HEAD')
    </p>
    <p>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_INVALIDAPI')
    </p>
    <p>
        @unless($isJoomla3)
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_INVALIDAPI_PLEASE_CHECK_VERSION')
        @else
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_INVALIDAPI_PLEASE_CHECK_VERSION_J3')
        @endunless
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_INVALIDAPI_OR_CHECK_ERRORS')
    </p>
@elseif($connectionError instanceof AkeebaBackupNoEndpoint)
    {{-- Cannot find an Akeeba Backup JSON API endpoint to connect to --}}
    <p class="fw-semibold">
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_HEAD')
    </p>
    <p>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_BODY')
    </p>
@else
    {{-- Akeeba Backup JSON API error --}}
    <p class="fw-semibold">
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_JSONAPI_ERROR_HEAD')
    </p>
    <p>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_JSONAPI_ERROR')
    </p>
@endif

{{-- As long as it's not a mere warning we can display further troubleshooting information --}}
@unless($isOnlyAWarning)
    <?php
    $session           = $this->getContainer()->segment;
    $step              = $session->get('testconnection.akeebabackup.step', null) ?: '';
    $http_status       = $session->get('testconnection.akeebabackup.http_status', null);
    $body              = $session->get('testconnection.akeebabackup.body', null);
    $headers           = $session->get('testconnection.akeebabackup.headers', null);
    $exceptionType     = get_class($connectionError);
    $exceptionMessage  = $connectionError->getMessage();
    $exceptionFile     = $connectionError->getFile();
    $exceptionLine     = $connectionError->getLine();
    $exceptionTrace    = $connectionError->getTraceAsString();
    $hasRequestDebug   = is_int($http_status) || is_string($body)
                         || (is_array($headers)
                             && !empty($headers));
    $hasExceptionDebug = true;
    ?>

    <p class="fw-semibold">
        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_INFO')
    </p>
    <p class="small text-info">
        <strong>Process step:</strong>
        {{{ $step }}}
    </p>

    @if ($hasRequestDebug)
        <table class="table" data-bs-theme="dark">
            <tbody>
            @if (is_int($http_status))
                <tr>
                    <th scope="row">
                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_STATUS')
                    </th>
                    <td>
                        {{{ $http_status }}}
                    </td>
                </tr>
            @endif
            @if (is_string($body))
                <tr>
                    <th scope="row">
                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_BODY')
                    </th>
                    <td>
                        <pre class="overflow-x-scroll">{{{ $body }}}</pre>
                    </td>
                </tr>
            @endif
            @if (is_array($headers) && !empty($headers))
                <tr>
                    <th scope="row">
                        @lang('HTTP Headers')
                    </th>
                    <td>
                        <dl>
                            @foreach($headers as $k => $v)
                                <dt>{{{$k}}}</dt>
                                <dd>
                                    @if (is_array($v) && count($v) === 1)
                                        {{{ array_pop($v) }}}
                                    @elseif (is_array($v))
                                        <ul>
                                            @foreach($v as $vv)
                                                <li>
                                                    @if (is_scalar($vv))
                                                        {{{ $vv }}}
                                                    @else
                                                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_HEADER_NO_STRING')
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif(is_string($v))
                                        {{{ $v }}}
                                    @else
                                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_HEADER_NO_PRINTABLE')
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    @endif

    @if ($hasExceptionDebug)
        @if((is_string($exceptionType) && !empty($exceptionType)))
            <p class="text-danger-emphasis">
                {{{ $exceptionType }}}
            </p>
        @endif
        @if(is_string($exceptionMessage) && !empty($exceptionMessage))
            <p>
                {{{ $exceptionMessage }}}
            </p>
        @endif
        @if(is_string($exceptionFile) && !empty($exceptionFile) && is_scalar($exceptionLine) && !empty($exceptionLine))
            <p>
                {{{ $exceptionFile }}}:{{{ $exceptionLine }}}
            </p>
        @endif
        @if((is_string($exceptionTrace) && !empty($exceptionTrace)))
            <pre class="overflow-x-scroll">{{{ $exceptionTrace }}}</pre>
        @endif
    @endif
@endunless
