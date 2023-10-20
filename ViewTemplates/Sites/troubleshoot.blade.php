<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');
?>
<div class="card my-3 border-info">
    <h3 class="card-header bg-info text-white">
        <span class="fa fa-bug-slash"></span>
        @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_HEAD')
    </h3>
    <div class="card-body bg-info-subtle">
        @if($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBlocked::class)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_HEAD')
            </p>
            <p>
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_WHATIS', htmlentities($this->item->url))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_TOCHECK')
            </p>
            <ul>
                <li>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK1', htmlentities($this->item->url))
                </li>
                <li>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK2', htmlentities($this->item->url), htmlentities($config->get('config.apiKey')), htmlentities(AKEEBA_PANOPTICON_VERSION))
                </li>
                <li>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_API403_CHECK3')
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBroken::class)
            <p class="fw-semibold">
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_HEAD', htmlentities($this->httpCode))
            </p>
            @if ($this->httpCode > 500)
                <p>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_5XX', htmlentities($this->httpCode))
                </p>
            @elseif($this->httpCode === 400)
                <p>
                    @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_HTTPERROR_400', htmlentities((new \Awf\Uri\Uri($this->item->url))->getHost()))
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
                <p>
                    @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_401_BLAH2')
                </p>
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIInvalidCredentials::class)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH2')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH3')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_APITOKEN_BLAH4')
            </p>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\cURLError::class)
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
        @elseif($this->connectionError === \GuzzleHttp\Exception\GuzzleException::class)
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName::class)
            <p class="fw-semibold">
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_DNS_HEAD', htmlentities((new \Awf\Uri\Uri($this->item->url))->getHost()))
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\PanopticonConnectorNotEnabled::class)
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL::class)
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem::class)
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
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\WebServicesInstallerNotEnabled::class)
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_BLAH1')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_WEBSERVICES_BLAH2')
            </p>
        @else
            <p class="fw-semibold">
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_HEAD')
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH1')
            </p>
            <p>
                @sprintf('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH2', htmlentities($this->connectionError))
            </p>
            <p>
                @lang('PANOPTICON_SITES_LBL_TROUBLESHOOT_FUBAR_BLAH3')
            </p>
        @endif
    </div>
</div>