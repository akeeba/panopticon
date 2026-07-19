<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

$forbiddenRanges = \Akeeba\Panopticon\Library\Security\ForbiddenIpRanges::normaliseList(
	$config->get('forbidden_ip_ranges', [])
);

// Always render at least one row, so there is something to type into on a fresh installation.
if (empty($forbiddenRanges))
{
	$forbiddenRanges = [''];
}

?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_SITELIMITS')</h3>
    <div class="card-body">
        {{-- forbidden_ip_ranges --}}
        <div class="row mb-3">
            <label for="forbidden_ip_ranges" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES')
            </label>
            <div class="col-sm-9">
                <div class="js-repeatable" id="forbidden_ip_ranges">
                    <div class="js-repeatable-rows">
                        @foreach($forbiddenRanges as $range)
                        <div class="js-repeatable-row input-group mb-2">
                            <input type="text" class="form-control" name="options[forbidden_ip_ranges][]"
                                   value="{{{ $range }}}"
                                   placeholder="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_PLACEHOLDER')"
                                   aria-label="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES')"
                            >
                            <button type="button" class="btn btn-outline-danger js-repeatable-remove"
                                    aria-label="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_REMOVE')"
                            >
                                <span class="fa fa-fw fa-trash-can" aria-hidden="true"></span>
                            </button>
                        </div>
                        @endforeach
                    </div>

                    <template class="js-repeatable-template">
                        <div class="js-repeatable-row input-group mb-2">
                            <input type="text" class="form-control" name="options[forbidden_ip_ranges][]"
                                   value=""
                                   placeholder="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_PLACEHOLDER')"
                                   aria-label="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES')"
                            >
                            <button type="button" class="btn btn-outline-danger js-repeatable-remove"
                                    aria-label="@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_REMOVE')"
                            >
                                <span class="fa fa-fw fa-trash-can" aria-hidden="true"></span>
                            </button>
                        </div>
                    </template>

                    <button type="button" class="btn btn-secondary btn-sm js-repeatable-add">
                        <span class="fa fa-fw fa-plus" aria-hidden="true"></span>
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_ADD')
                    </button>
                </div>

                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FORBIDDEN_IP_RANGES_HELP')
                </div>
            </div>
        </div>
    </div>
</div>
