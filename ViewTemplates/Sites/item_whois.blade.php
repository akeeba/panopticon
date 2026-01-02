<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$domain         = $this->siteConfig->get('whois.domain', null)
	?: Uri::getInstance($this->item->getBaseUrl())->getHost();
$created        = $this->siteConfig->get('whois.created', null);
$expiration     = $this->siteConfig->get('whois.expiration', null);
$registrar      = $this->siteConfig->get('whois.registrar', null);
$nameservers    = $this->siteConfig->get('whois.nameservers', []) ?: [];
$validityStatus = $this->item->getDomainValidityStatus();

$hasError   = !in_array($validityStatus, [0, 2]);
$hasWarning = !$hasError && $validityStatus === 2;

?>
<button type="button"
        class="btn {{ $hasError ? 'btn-outline-danger' : ($hasWarning ? 'btn-outline-warning' : 'btn-outline-success') }}"
        data-bs-toggle="modal" data-bs-target="#whoisInfoModal"
>
    <span class="fa fa-fw fa-globe" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="bottom"
          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO')"></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO')</span>
</button>

<div class="modal fade fs-6 fw-normal text-start" id="whoisInfoModal" tabindex="-1"
     aria-labelledby="whoisInfoModal_label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5" id="sslTlsInfoModal_label">
                    @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO')
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
            </div>
            <div class="modal-body">
                @if($validityStatus === -1)
                    <div class="alert alert-warning">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_ERR_DATE_INVALID_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_ERR_DATE_INVALID')
                    </div>
                @elseif($validityStatus === 1)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_TOOSOON_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_TOOSOON')
                    </div>
                @elseif($validityStatus === 2)
                    <div class="alert alert-warning">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_EXPIRING_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_EXPIRING')
                    </div>
                @elseif($validityStatus === 3)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_EXPIRED_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_EXPIRED')
                    </div>
                @elseif(empty($nameservers))
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_NONAMESERVERS_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_NONAMESERVERS')
                    </div>
                @endif

                <table class="table">
                    <caption class="visually-hidden">
                        @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_TABLE_CAPTION')
                    </caption>
                    <tbody>
                    <tr>
                        <th scope="row">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_DOMAIN')</th>
                        <td>
                            {{{ $domain }}}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_REGISTERED')</th>
                        <td>
                            @if (empty($created))
                                &ndash;
                            @else
                                {{ $this->getContainer()->html->basic->date($created, $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_EXPIRES')</th>
                        <td>
                            @if (empty($expiration))
                                &ndash;
                            @else
                                {{ $this->getContainer()->html->basic->date($expiration, $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_REGISTRAR')</th>
                        <td>
                            {{{ $registrar }}}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">@lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_NAMESERVERS')</th>
                        <td>
                            @if (empty($nameservers))
                                &ndash;
                            @else
                                <ul>
                                    @foreach($nameservers as $ns)
                                        <li>
                                            {{{ $ns }}}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                    </tbody>
                </table>

                <div class="my-3 text-muted small">
                    @lang('PANOPTICON_MAIN_SITES_LBL_WHOISINFO_CACHING_NOTICE')
                </div>

            </div>
        </div>
    </div>
</div>