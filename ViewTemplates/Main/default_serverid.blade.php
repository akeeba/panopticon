<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 */

?>
<div class="card border-info my-3">
    <h3 class="card-header bg-info text-light d-flex flex-column align-items-center flex-sm-row gap-2 fs-5">
        <span class="fa fa-id-badge" aria-hidden="true"></span>
        <span>@lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_HEAD')</span>
    </h3>
    <div class="card-body">

        <div class="alert alert-info">
            <h4 class="alert-heading fs-5">@lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_ALERT_HEAD')</h4>
            @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_ALERT_BODY')
        </div>

        <table class="table">
            <tbody>
            <tr>
                <th scope="row">
                    <span class="fa fa-globe" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_PUB_HOSTNAME')
                </th>
                <td>
                    {{{ $_SERVER['HTTP_HOST'] ?: '<span class="badge bg-danger">' . $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_UNKNOWN_NEUTRAL') . '</span>' }}}
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <span class="fa fa-network-wired" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_PUB_IP')
                </th>
                <td>
                    {{{ gethostbyname($_SERVER['HTTP_HOST']) ?: '<span class="badge bg-danger">' . $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_UNKNOWN_NEUTRAL') . '</span>' }}}
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <span class="fa fa-server" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_SYS_HOSTNAME')
                </th>
                <td>
                    {{{ php_uname('n') ?: '<span class="badge bg-danger">' . $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_UNKNOWN_NEUTRAL') . '</span>' }}}
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <span class="fa fa-ethernet" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_SYS_IP')
                </th>
                <td>
                    {{{ gethostbyname(php_uname('n')) ?: '<span class="badge bg-danger">' . $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_UNKNOWN_NEUTRAL') . '</span>' }}}
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <span class="fa fa-user-secret" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_IDENTITY_USER_AGENT')
                </th>
                <td>
                    {{{ 'panopticon/' . AKEEBA_PANOPTICON_VERSION  }}}
                </td>
            </tr>
            </tbody>
        </table>

    </div>
</div>