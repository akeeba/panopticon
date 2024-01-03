<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$validFrom      = $this->item->getSSLValidityDate(true);
$validTo        = $this->item->getSSLValidityDate(false);
$validityStatus = $this->item->getSSLValidityStatus();
$validDomain    = $this->item->getSSLValidDomain();
$verified       = $this->siteConfig->get('ssl.verified');

$hasError       = !in_array($validityStatus, [0,2]) || !$validDomain || !$verified;
$hasWarning     = !$hasError && $validityStatus === 2;

?>
<button type="button"
        class="btn {{ $hasError ? 'btn-outline-danger' : ($hasWarning ? 'btn-outline-warning' : 'btn-outline-success') }}"
        data-bs-toggle="modal" data-bs-target="#sslTlsInfoModal"
>
    <span class="fa fa-fw fa-lock" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="bottom"
          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_SSL_TLS_CERT_INFO')"></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_SSL_TLS_CERT_INFO')</span>
</button>

<div class="modal fade fs-6 fw-normal text-start" id="sslTlsInfoModal" tabindex="-1"
     aria-labelledby="sslTlsInfoModal_label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5" id="sslTlsInfoModal_label">
                    @lang('PANOPTICON_MAIN_SITES_LBL_SSL_TLS_CERT_INFO')
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
            </div>
            <div class="modal-body">
                @if($validityStatus === -1)
                    <div class="alert alert-warning">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_ERR_DATE_INVALID_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_ERR_DATE_INVALID')
                    </div>
                @elseif($validityStatus === 1)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_TOOSOON_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_TOOSOON')
                    </div>
                @elseif($validityStatus === 2)
                    <div class="alert alert-warning">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-triangle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_EXPIRING_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_EXPIRING')
                    </div>
                @elseif($validityStatus === 3)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_EXPIRED_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_EXPIRED')
                    </div>
                @elseif(!$validDomain)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_INVALID_CN_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_INVALID_CN')
                    </div>
                @elseif(!$verified)
                    <div class="alert alert-danger">
                        <h4 class="alert-heading fs-6">
                            <span class="fa fa-fw fa-circle-exclamation" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_INVALID_SIG_HEAD')
                        </h4>
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_INVALID_SIG')
                    </div>
                @endif

                <table class="table">
                    <caption class="visually-hidden">
                        @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_TABLE_CAPTION')
                    </caption>
                    <tbody>
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_SERIAL')
                        </th>
                        <td>
                            {{{ $this->siteConfig->get('ssl.serialHex') }}}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_SIG_TYPE')
                        </th>
                        <td>
                            {{{ $this->siteConfig->get('ssl.type') }}}
                        </td>
                    </tr>
                    @if ($validFrom)
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_FROM')
                        </th>
	                    <?php
	                    $class = match ($validityStatus) {
		                    -1, 1 => 'text-danger',
		                    2 => 'text-warning',
		                    0 => 'text-success',
		                    default => ''
	                    }
	                    ?>
                        <td class="{{ $class }}">
                            {{ $this->getContainer()->html->basic->date($this->siteConfig->get('ssl.validFrom'), $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                        </td>
                    </tr>
                    @endif
                    @if ($validTo)
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_TO')
                        </th>
                        <?php
                        $class = match ($validityStatus) {
                            -1, 3 => 'text-danger',
                            2 => 'text-warning',
                            0 => 'text-success',
                            default => ''
                        }
                        ?>
                        <td class="{{ $class }}">
                            {{ $this->getContainer()->html->basic->date($this->siteConfig->get('ssl.validTo'), $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_CN')
                        </th>
                        <td class="{{ $validDomain ? 'text-success' : 'text-warning' }}">
                            @unless (count($domains = $this->siteConfig->get('ssl.commonName')) === 1)
                                <ul>
                                    @foreach ($domains as $domain)
                                        <li>{{{ $domain }}}</li>
                                    @endforeach
                                </ul>
                            @else
                                {{{ reset($domains) }}}
                            @endunless
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            @lang('PANOPTICON_MAIN_SITES_LBL_SSLTLS_ISSUER')
                        </th>
                        <td class="{{ $verified ? 'text-success' : '' }}">
                            {{{ $this->siteConfig->get('ssl.issuerOrganisation') }}}
                            –
                            {{{ $this->siteConfig->get('ssl.issuerCommonName') }}}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    @lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
                </button>
            </div>
        </div>
    </div>
</div>