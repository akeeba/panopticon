<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationHasPHPMessages;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBlocked;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBroken;
use Akeeba\Panopticon\Exception\SiteConnection\APIInvalidCredentials;
use Akeeba\Panopticon\Exception\SiteConnection\cURLError;
use Akeeba\Panopticon\Exception\SiteConnection\FrontendPasswordProtection;
use Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName;
use Akeeba\Panopticon\Exception\SiteConnection\PanopticonConnectorNotEnabled;
use Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL;
use Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem;
use Akeeba\Panopticon\Exception\SiteConnection\WebServicesInstallerNotEnabled;
use Awf\Uri\Uri;
use GuzzleHttp\Exception\GuzzleException;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */
$site               ??= $this->item;
$connectionError    ??= $this->connectionError;
$forceDebug         ??= $this->container->appConfig->get('debug', false);
$showHeader         ??= true;
$border             ??= 'border-info';
$background         ??= 'bg-info-subtle';
$config             = $site->getConfig();
$isJoomla3          = str_ends_with(rtrim($site->url, '/'), '/panopticon_api');
$maybeJ3NeedsIndex  = $isJoomla3 && !str_contains($site->url, '/index.php/panopticon_api');
$possibleJ3Endpoint = $maybeJ3NeedsIndex
	? str_replace('/panopticon_api', '/index.php/panopticon_api', $site->url)
	: $site->url;
?>
<div class="card my-3 {{ $border }}">
    @if($showHeader)
        <h3 class="card-header bg-info text-white">
            <span class="fa fa-bug-slash"></span>
            @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_HEAD')
        </h3>
    @endif
    <div class="card-body {{ $background }}">
        @if($connectionError instanceof APIApplicationIsBlocked)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_HEAD')
            </p>
            <p>
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_WHATIS' . ($isJoomla3 ? '_J3' : ''), htmlentities($site->url))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_TOCHECK')
            </p>
            <ul>
                <li>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK1' . ($isJoomla3 ? '_J3' : ''), htmlentities($site->url))
                </li>
                <li>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK2' . ($isJoomla3 ? '_J3' : ''), htmlentities($site->url), htmlentities($config->get('config.apiKey')), htmlentities(AKEEBA_PANOPTICON_VERSION))
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK3')
                </li>
            </ul>
        @elseif($connectionError instanceof APIApplicationHasPHPMessages)
            <p class="fw-semibold">
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_DIRTYOUTPUT_HEAD' . ($isJoomla3 ? '_J3' : ''), htmlentities($this->httpCode))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DIRTYOUTPUT_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DIRTYOUTPUT_BLAH2')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DIRTYOUTPUT_BLAH3')
            </p>
        @elseif($connectionError instanceof APIApplicationIsBroken)
            <p class="fw-semibold">
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_HEAD' . ($isJoomla3 ? '_J3' : ''), htmlentities($this->httpCode))
            </p>
            @if ($this->httpCode > 500)
                <p>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_5XX' . ($isJoomla3 ? '_J3' : ''), htmlentities($this->httpCode))
                </p>
            @elseif($this->httpCode === 400)
                <p>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_400', htmlentities((new Uri($site->url))->getHost()))
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_400_ATPRO')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_400_WHATEVER')
                </p>
            @elseif($this->httpCode === 401)
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_HEAD')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH1')
                </p>
                @if (!$isJoomla3)
                    <p>
                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH2')
                    </p>
                @else
                    <p>
                        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH2_J3')
                    </p>
                @endif
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH3')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH4')
                </p>
            @elseif($this->httpCode === 406)
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_406_BLAH1')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_406_BLAH2')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_406_BLAH3')
                </p>
            @else
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_HUH_GENERIC')
                </p>
            @endif
        @elseif($connectionError instanceof APIInvalidCredentials)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_HEAD')
            </p>
            @if(!$isJoomla3)
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH1')
                </p>
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH2')
                </p>
            @else
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH2_J3')
                </p>
            @endif
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH3')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH4')
            </p>
        @elseif($connectionError instanceof cURLError)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CURL_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CURL_BLAH1')
            </p>
            <p class="px-2 text-secondary">
                {{{ $this->curlError }}}
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CURL_BLAH2')
            </p>
        @elseif($connectionError instanceof GuzzleException)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_GUZZLE_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_GUZZLE_BLAH1')
            </p>
            <p class="px-2 text-secondary">
                {{{ $this->guzzleError }}}
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_GUZZLE_BLAH2')
            </p>
        @elseif($connectionError instanceof InvalidHostName)
            <p class="fw-semibold">
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_HEAD', htmlentities((new Uri($site->url))->getHost()))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_CHECK')
            </p>
            <ul>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_CHECK1')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_CHECK2')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_CHECK3')
                </li>
            </ul>
        @elseif($connectionError instanceof PanopticonConnectorNotEnabled && !$isJoomla3)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_BLAH')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_CHECK')
            </p>
            <ul>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_CHECK1')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_CHECK2')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_CHECK3')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_CHECK4')
                </li>
            </ul>
        @elseif($connectionError instanceof PanopticonConnectorNotEnabled && $isJoomla3)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_J3_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_CONNECTOR_J3_BLAH')
            </p>
        @elseif($connectionError instanceof SelfSignedSSL)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_WHAT')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_CHECK')
            </p>
            <ul>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_CHECK1')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_CHECK2')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLS_SELFSIGNED_CHECK3')
                </li>
            </ul>
        @elseif($connectionError instanceof SSLCertificateProblem)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_WHAT')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_CHECK')
            </p>
            <ul>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_CHECK1')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_CHECK2')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_CHECK3')
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_TLSBORKED_CHECK4')
                </li>
            </ul>
        @elseif($connectionError instanceof WebServicesInstallerNotEnabled && !$isJoomla3)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_BLAH2')
            </p>
        @elseif($connectionError instanceof WebServicesInstallerNotEnabled && $isJoomla3)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PLUGIN_J3_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PLUGIN_J3_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PLUGIN_J3_BLAH2')
            </p>
            @if($maybeJ3NeedsIndex)
                <p>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_PLUGIN_J3_BLAH3', $possibleJ3Endpoint)
                </p>
            @endif
        @elseif($connectionError instanceof FrontendPasswordProtection)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PWPROTECT_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PWPROTECT_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PWPROTECT_BLAH2')
            </p>
            @if (!$isJoomla3)
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PWPROTECT_BLAH3')
                </p>
            @else
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_PWPROTECT_BLAH3_J3')
                </p>
            @endif
        @else
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH1')
            </p>
            <p>
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH2', htmlentities(($connectionError instanceof Throwable) ? $connectionError->getMessage() : ($connectionError ?? 'NULL')))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH3')
            </p>
        @endif

        @if($forceDebug)
				<?php
				$session           = $this->getContainer()->segment;
				$step              = $session->get('testconnection.step', null) ?: '';
				$http_status       = $session->get('testconnection.http_status', null);
				$body              = $session->get('testconnection.body', null);
				$headers           = $session->get('testconnection.headers', null);
				$exceptionType     = $session->get('testconnection.exception.type', $connectionError instanceof Throwable ? get_class($connectionError) : null);
				$exceptionMessage  = $session->get('testconnection.exception.message', $connectionError instanceof Throwable ? $connectionError->getMessage() : null);
				$exceptionFile     = $session->get('testconnection.exception.file', $connectionError instanceof Throwable ? $connectionError->getFile() : null);
				$exceptionLine     = $session->get('testconnection.exception.line', $connectionError instanceof Throwable ? $connectionError->getLine() : null);
				$exceptionTrace    = $session->get('testconnection.exception.trace', $connectionError instanceof Throwable ? $connectionError->getTraceAsString() : null);
				$hasRequestDebug   = is_int($http_status) || is_string($body)
				                     || (is_array($headers)
				                         && !empty($headers));
				$hasExceptionDebug = (is_string($exceptionType) && !empty($exceptionType))
				                     || (is_string($exceptionMessage) && !empty($exceptionMessage))
				                     || (is_string($exceptionFile) && !empty($exceptionFile))
				                     || (is_scalar($exceptionLine) && !empty($exceptionLine))
				                     || (is_string($exceptionTrace) && !empty($exceptionTrace));
				?>

            @if ($hasRequestDebug || $hasExceptionDebug)
                <hr class="my-3">
                <p class="fw-semibold">
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_INFO')
                </p>
                <p class="small text-info">
                    <strong>Process step:</strong>
                    {{{ $step }}}
                </p>
            @endif

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
                                <pre style="overflow-x: scroll">{{{ $body }}}</pre>
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
                    <pre style="overflow-x: scroll">{{{ $exceptionTrace }}}</pre>
                @endif
            @endif

        @else
            <p class="text-info small">
                <span class="fa fa-fw fa-circle-info" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_DEBUG_MUST_ENABLE_FOR_ISSUE')
            </p>
        @endif

    </div>
</div>