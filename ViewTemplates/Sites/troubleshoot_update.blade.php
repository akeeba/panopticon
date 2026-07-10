<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$results     ??= $this->updateDoctorResults;
$forceDebug  ??= $this->container->appConfig->get('debug', false);
$token       = $this->container->session->getCsrfToken()->getValue();
$isSuper     = $this->getContainer()->userManager->getUser()->getPrivilege('panopticon.super', false);
$extract     = $results->extract ?? null;
$finding     = $results->logFinding ?? null;
$report      = $results->report ?? null;

// Log finding category => [heading language key, advice language key]
$logCategoryMap = [
	'extraction_blocked' => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_BLOCKED_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_BLOCKED_BODY'],
	'admin_auth'         => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_AUTH_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_AUTH_BODY'],
	'invalid_json'       => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_JSON_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_JSON_BODY'],
	'extraction_failed'  => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_EXTRACT_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_EXTRACT_BODY'],
	'download_failed'    => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_DOWNLOAD_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_DOWNLOAD_BODY'],
	'invalid_checksum'   => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_CHECKSUM_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_CHECKSUM_BODY'],
	'backup_failed'      => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_BACKUP_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_BACKUP_BODY'],
	'enable_failed'      => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_ENABLE_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_ENABLE_BODY'],
	'update_disappeared' => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_DISAPPEARED_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_DISAPPEARED_BODY'],
	'finalise_failed'    => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_FINALISE_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_FINALISE_BODY'],
	'reload_failed'      => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_RELOAD_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_RELOAD_BODY'],
	'factory'            => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_FACTORY_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_FACTORY_BODY'],
	'timeout'            => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_TIMEOUT_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_TIMEOUT_BODY'],
	'unknown'            => ['PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_UNKNOWN_HEAD', 'PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_UNKNOWN_BODY'],
];
?>

<div class="card my-3">
    <h3 class="card-header bg-info text-white">
        <span class="fa fa-screwdriver-wrench" aria-hidden="true"></span>
        @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_TITLE')
    </h3>
    <div class="card-body">

        {{-- Check 1: JSON API reachability (we only get here when it is reachable) --}}
        <div class="d-flex flex-row gap-3 align-items-start py-2 border-bottom">
            <div class="fs-4 text-success"><span class="fa fa-fw fa-circle-check" aria-hidden="true"></span></div>
            <div class="flex-grow-1">
                <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_API_OK')</div>
            </div>
        </div>

        {{-- Check 2: extraction endpoint (extract.php / restore.php) --}}
        <div class="d-flex flex-row gap-3 align-items-start py-2 border-bottom">
            @if ($extract?->status === 'ok')
                <div class="fs-4 text-success"><span class="fa fa-fw fa-circle-check" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_OK')</div>
                    <div class="small text-muted">{{{ $extract->endpoint }}}</div>
                </div>
            @elseif ($extract?->status === 'auth')
                <div class="fs-4 text-warning"><span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_AUTH_HEAD')</div>
                    <p>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_AUTH_BODY')</p>
                </div>
            @elseif ($extract?->status === 'blocked')
                <div class="fs-4 text-danger"><span class="fa fa-fw fa-ban" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold text-danger-emphasis">
                        @sprintf('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_BLOCKED_HEAD', (int) $extract->httpStatus)
                    </div>
                    <p>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_BLOCKED_BODY')</p>
                    <ul>
                        <li>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_BLOCKED_CHECK1')</li>
                        <li>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_BLOCKED_CHECK2')</li>
                        <li>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_BLOCKED_CHECK3')</li>
                    </ul>
                    <p class="small text-muted">
                        @sprintf('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_ENDPOINT', htmlentities($extract->endpoint))
                    </p>
                </div>
            @else
                <div class="fs-4 text-danger"><span class="fa fa-fw fa-plug-circle-exclamation" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold text-danger-emphasis">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_ERROR_HEAD')</div>
                    <p>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_CHECK_EXTRACT_ERROR_BODY')</p>
                    @if ($extract?->exception)
                        <p class="small text-muted">{{{ $extract->exception->getMessage() }}}</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Check 3: the site's Joomla update task log --}}
        <div class="d-flex flex-row gap-3 align-items-start py-2 border-bottom">
            @if ($results->logMissing)
                <div class="fs-4 text-info"><span class="fa fa-fw fa-circle-info" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_MISSING_HEAD')</div>
                    <p>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_MISSING_BODY')</p>
                    @unless ($results->logLevelDebug)
                        <p class="text-warning-emphasis">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_NEED_DEBUG')
                        </p>
                    @endunless
                </div>
            @elseif ($finding !== null)
                <?php [$headKey, $bodyKey] = $logCategoryMap[$finding->category] ?? $logCategoryMap['unknown']; ?>
                <div class="fs-4 text-danger"><span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold text-danger-emphasis">
                        @if ($finding->category === 'extraction_blocked' && $finding->httpCode)
                            @sprintf($headKey, (int) $finding->httpCode)
                        @else
                            @lang($headKey)
                        @endif
                    </div>
                    <p>@lang($bodyKey)</p>
                    <p class="small text-muted mb-1">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_LAST_ERROR')</p>
                    <pre class="overflow-x-auto small bg-body-tertiary p-2 rounded">{{{ $finding->message }}}</pre>
                </div>
            @else
                <div class="fs-4 text-success"><span class="fa fa-fw fa-circle-check" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_LOG_CLEAN')</div>
                </div>
            @endif
        </div>

        {{-- Check 4: latest core update report corroboration --}}
        @if ($report !== null && $report->success === false)
            <div class="d-flex flex-row gap-3 align-items-start py-2 border-bottom">
                <div class="fs-4 text-secondary"><span class="fa fa-fw fa-clipboard-list" aria-hidden="true"></span></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_REPORT_HEAD')</div>
                    <ul class="mb-0">
                        @if (!empty($report->failedStep))
                            <li>@sprintf('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_REPORT_STEP', htmlentities((string) $report->failedStep))</li>
                        @endif
                        @if (!empty($report->httpCode))
                            <li>@sprintf('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_REPORT_HTTP', (int) $report->httpCode)</li>
                        @endif
                        <li>
                            @if ($report->backupOnUpdate)
                                @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_REPORT_BACKUP_ON')
                            @else
                                @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_REPORT_BACKUP_OFF')
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="d-flex flex-row flex-wrap gap-2 mt-3">
            <a href="@route(sprintf('index.php?view=site&task=scheduleJoomlaUpdate&id=%d&force=1&%s=1', $this->item->id, $token))"
               class="btn btn-primary" role="button">
                <span class="fa fa-rotate" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_RESCHEDULE')
            </a>

            @if (!$results->logMissing)
                <a href="@route(sprintf('index.php?view=log&task=read&logfile=%s', urlencode($results->logFileName)))"
                   class="btn btn-outline-secondary" role="button">
                    <span class="fa fa-file-lines" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_VIEW_LOG')
                </a>
            @endif

            @if ($isSuper && !$results->logLevelDebug)
                <a href="@route(sprintf('index.php?view=site&task=setDebugLogging&id=%d&%s=1', $this->item->id, $token))"
                   class="btn btn-outline-warning" role="button">
                    <span class="fa fa-bug" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_ENABLE_DEBUG')
                </a>
            @endif
        </div>
        @if ($isSuper && !$results->logLevelDebug)
            <p class="small text-muted mt-1 mb-0">
                <span class="fa fa-fw fa-triangle-exclamation text-warning" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_ENABLE_DEBUG_WARN')
            </p>
        @endif

        {{-- Debug dump of the extraction endpoint probe --}}
        @if($forceDebug)
			<?php
			$session          = $this->getContainer()->segment;
			$http_status      = $session->get('updatedoctor.http_status', null);
			$body             = $session->get('updatedoctor.body', null);
			$headers          = $session->get('updatedoctor.headers', null);
			$exceptionType    = $session->get('updatedoctor.exception.type', null);
			$exceptionMessage = $session->get('updatedoctor.exception.message', null);
			$exceptionFile    = $session->get('updatedoctor.exception.file', null);
			$exceptionLine    = $session->get('updatedoctor.exception.line', null);
			$exceptionTrace   = $session->get('updatedoctor.exception.trace', null);
			$hasRequestDebug  = is_int($http_status) || is_string($body) || (is_array($headers) && !empty($headers));
			$hasExceptionDebug = (is_string($exceptionType) && !empty($exceptionType))
			                     || (is_string($exceptionMessage) && !empty($exceptionMessage))
			                     || (is_string($exceptionTrace) && !empty($exceptionTrace));
			?>
            @if ($hasRequestDebug || $hasExceptionDebug)
                <hr class="my-3">
                <p class="fw-semibold">@lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_INFO')</p>
            @endif

            @if ($hasRequestDebug)
                <table class="table" data-bs-theme="dark">
                    <tbody>
                    @if (is_int($http_status))
                        <tr>
                            <th scope="row">@lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_STATUS')</th>
                            <td>{{{ $http_status }}}</td>
                        </tr>
                    @endif
                    @if (is_string($body))
                        <tr>
                            <th scope="row">@lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_BODY')</th>
                            <td><pre class="overflow-x-scroll">{{{ $body }}}</pre></td>
                        </tr>
                    @endif
                    @if (is_array($headers) && !empty($headers))
                        <tr>
                            <th scope="row">@lang('HTTP Headers')</th>
                            <td>
                                <dl>
                                    @foreach($headers as $k => $v)
                                        <dt>{{{ $k }}}</dt>
                                        <dd>
                                            @if (is_array($v) && count($v) === 1)
                                                {{{ array_pop($v) }}}
                                            @elseif (is_array($v))
                                                <ul>
                                                    @foreach($v as $vv)
                                                        <li>@if (is_scalar($vv)){{{ $vv }}}@else @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_HTTP_HEADER_NO_STRING')@endif</li>
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
                @if(is_string($exceptionType) && !empty($exceptionType))
                    <p class="text-danger-emphasis">{{{ $exceptionType }}}</p>
                @endif
                @if(is_string($exceptionMessage) && !empty($exceptionMessage))
                    <p>{{{ $exceptionMessage }}}</p>
                @endif
                @if(is_string($exceptionFile) && !empty($exceptionFile) && is_scalar($exceptionLine) && !empty($exceptionLine))
                    <p>{{{ $exceptionFile }}}:{{{ $exceptionLine }}}</p>
                @endif
                @if(is_string($exceptionTrace) && !empty($exceptionTrace))
                    <pre class="overflow-x-scroll">{{{ $exceptionTrace }}}</pre>
                @endif
            @endif
        @endif

    </div>
</div>
