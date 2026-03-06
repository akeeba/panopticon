<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Enumerations\CMSType;

defined('AKEEBA') || die;

?>
<?php
/** @var \Akeeba\Panopticon\Model\Site $item */
?>
            @foreach($this->items as $item)
					<?php
					$url               = $item->getBaseUrl();
					$config            = $item->getConfig();
					$favicon           = $item->getFavicon(asDataUrl: true, onlyIfCached: true);
					$certificateStatus = $item->getSSLValidityStatus();
					$uptimeStatus      = $this->getContainer()->helper->uptime->status($item);
					?>
                <tr>
                    <td>
                        <div class="d-flex flex-row gap-2">
                            @if ($favicon)
                                <div class="d-none d-md-block text-center" style="width: 1.3em">
                                    <img alt="" aria-hidden="true"
                                         src="{{{ $favicon }}}"
                                         class="me-1"
                                         style="aspect-ratio: 1.0; max-width: 1.3em; max-height: 1.3em; min-width: 1em; min-height: 1em">
                                </div>
                            @endif
                            <div>
                                <div>
                                    @if (!$uptimeStatus->up && !$uptimeStatus->isScheduled)
                                        <div class="d-inline-block bg-danger rounded-pill px-1 text-bg-dark">
                                            @if ($uptimeStatus->detailsUrl)
                                                <a href="{{ $uptimeStatus->detailsUrl }}">
                                                    <span class="fa fa-fw fa-arrow-down" aria-hidden="true"></span>
                                                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_DOWN')</span>
                                                </a>
                                            @else
                                                <span class="fa fa-fw fa-arrow-down" aria-hidden="true"></span>
                                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_DOWN')</span>
                                            @endif
                                        </div>
                                    @elseif (!$uptimeStatus->up)
                                        <div class="d-inline-block bg-warning rounded-pill px-1 text-bg-light">
                                            @if ($uptimeStatus->detailsUrl)
                                                <a href="{{ $uptimeStatus->detailsUrl }}">
                                                    <span class="fa fa-fw fa-hammer" aria-hidden="true"></span>
                                                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_MAINTENANCE')</span>
                                                </a>
                                            @else
                                                <span class="fa fa-fw fa-hammer" aria-hidden="true"></span>
                                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_MAINTENANCE')</span>
                                            @endif
                                        </div>
                                    @endif

                                    <a class="fw-medium {{ $uptimeStatus->up ? '' : 'text-danger fw-bold' }}"
                                       href="@route(sprintf('index.php?view=site&task=read&id=%s', $item->id))">
                                        {{{ $item->name }}}
                                    </a>
                                </div>
                                <div class="small mt-1">
                                    @if(in_array($certificateStatus, [-1, 1, 3]))
                                        <span class="fa fa-fw fa-lock text-danger" aria-hidden="true"
                                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                                              data-bs-title="@lang('PANOPTICON_MAIN_DASH_ERR_CERT_INVALID')"
                                        ></span>
                                        <span class="visually-hidden">
                                        @lang('PANOPTICON_MAIN_DASH_ERR_CERT_INVALID')
                                    </span>
                                    @elseif($certificateStatus === 2)
                                        <span class="fa fa-fw fa-lock text-warning" aria-hidden="true"
                                              data-bs-toggle="tooltip" data-bs-placement="bottom"
                                              data-bs-title="@lang('PANOPTICON_MAIN_DASH_ERR_CERT_EXPIRING')"
                                        ></span>
                                        <span class="visually-hidden">
                                        @lang('PANOPTICON_MAIN_DASH_ERR_CERT_EXPIRING')
                                    </span>
                                    @endif
                                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                                    <a href="{{{ $url }}}" class="link-secondary text-decoration-none" target="_blank">
                                        {{{ $url }}}
                                        <span class="fa fa-external-link-alt fa-xs text-muted"
                                              aria-hidden="true"></span>
                                    </a>
                                </div>
                                {{-- Show group labels --}}
                                @if (!empty($groups = $config->get('config.groups')))
                                    <div>
                                        @foreach($groups as $gid)
                                            @if (isset($this->groupMap[$gid]))
                                                <span class="badge bg-secondary">
                                        {{{ $this->groupMap[$gid] }}}
                                    </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($item->cmsType() === CMSType::JOOMLA)
                            @include('Main/site_joomla', [
                                'item' => $item,
                                'config' => $config,
                            ])
                        @else
                            @include('Main/site_wordpress', [
                                'item' => $item,
                                'config' => $config,
                            ])
                        @endif
                    </td>
                    <td>
                        @include('Main/site_extensions', [
                            'item' => $item,
                            'config' => $config,
                        ])
                    </td>
                    <td class="d-none d-md-table-cell">
                        @include('Main/site_php', [
                            'item' => $item,
                            'config' => $config,
                            'php' => $config->get('core.php')
                        ])
                    </td>
                    <td class="d-none d-md-table-cell">
                        @include('Main/site_backup', [
                            'item' => $item,
                            'config' => $config,
                        ])
                    </td>
                    <td class="d-none d-md-table-cell font-monospace text-body-tertiary small px-2">
                        {{{ $item->id }}}
                    </td>
                </tr>
            @endforeach
            @if ($this->itemsCount == 0)
                <tr>
                    <td colspan="20">
                        <div class="alert alert-info m-2">
                            <span class="fa fa-info-circle" aria-hidden="true"></span>
                            @lang('PANOPTICON_MAIN_SITES_LBL_NO_RESULTS')
                        </div>
                    </td>
                </tr>
            @endif
