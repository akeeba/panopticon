<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Apitokens\Html $this
 * @var \Akeeba\Panopticon\Model\Apitoken      $model
 */
$model  = $this->getModel();
$isNew  = empty($model->id);

// Default the expiry: +1 year for new tokens, the stored value for existing ones.
if ($isNew)
{
	$defaultExpiry = date('Y-m-d\TH:i', strtotime('+1 year'));
}
else
{
	$expiresRaw = $model->expires_at;

	if (empty($expiresRaw) || $expiresRaw === '0000-00-00 00:00:00')
	{
		$defaultExpiry = '';
	}
	else
	{
		try
		{
			$defaultExpiry = $this->getContainer()->dateFactory($expiresRaw)->format('Y-m-d\TH:i', true);
		}
		catch (\Throwable)
		{
			$defaultExpiry = '';
		}
	}
}

$lastUsedIpDisplay = '';

if (!empty($model->last_used_ip))
{
	$printableIp = @inet_ntop($model->last_used_ip);

	if ($printableIp !== false && $printableIp !== null)
	{
		$lastUsedIpDisplay = $printableIp;
	}
}

$userManager = $this->getContainer()->userManager;

$formatUserRef = function (?int $userId) use ($userManager): string {
	if (empty($userId))
	{
		return '&mdash;';
	}

	$user = $userManager->getUser($userId);

	if (!$user || !$user->getId())
	{
		return '#' . (int) $userId;
	}

	$username = $user->getUsername();
	$fullName = $user->getName();
	$out      = '#' . (int) $userId;

	if ($username !== '' && $username !== null)
	{
		$out .= ' ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
	}

	if ($fullName !== '' && $fullName !== null && $fullName !== $username)
	{
		$out .= ' <span class="text-muted">(' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ')</span>';
	}

	return $out;
};
?>

<form action="@route('index.php?view=apitoken')" method="post" name="adminForm" id="adminForm" class="akeeba-panopticon-form">

    @if ($isNew)
        {{-- API Endpoint URLs (collapsed by default) --}}
        <div class="card mb-3">
            <h5 class="card-header h5 d-flex flex-row gap-1 align-items-center">
                <span class="fa fa-link" aria-hidden="true"></span>
                <span class="flex-grow-1">
                    @lang('PANOPTICON_APITOKENS_LBL_API_URL')
                </span>
                <button class="btn btn-success btn-sm ms-2" type="button"
                        data-bs-toggle="collapse" data-bs-target="#cardApiEndpointBody"
                        aria-expanded="false" aria-controls="cardApiEndpointBody"
                        data-bs-tooltip="tooltip" data-bs-placement="bottom"
                        data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')">
                    <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
                </button>
            </h5>
            <div class="card-body collapse" id="cardApiEndpointBody">
                <label for="apiUrl" class="form-label small mb-1">
                    @lang('PANOPTICON_APITOKENS_LBL_ENDPOINT_WITHHTACCESS')
                </label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace" value="{{{ $this->apiUrl }}}" readonly id="apiUrl">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('apiUrl').value)">
                        <span class="fa fa-copy" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_APITOKENS_LBL_COPY')</span>
                    </button>
                </div>

                <label for="apiUrlFallback" class="form-label small mb-1">
                    @lang('PANOPTICON_APITOKENS_LBL_ENDPOINT_WITHOUTHTACCESS')
                </label>
                <div class="input-group mb-2">
                    <input type="text" class="form-control font-monospace" value="{{{ $this->apiUrlFallback }}}" readonly id="apiUrlFallback">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('apiUrlFallback').value)">
                        <span class="fa fa-copy" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_APITOKENS_LBL_COPY')</span>
                    </button>
                </div>

                <p class="card-text text-muted small mb-0">
                    @lang('PANOPTICON_APITOKENS_LBL_API_URL_NOTE')
                </p>
            </div>
        </div>

        {{-- Security warning --}}
        <div class="alert alert-warning">
            <span class="fa fa-triangle-exclamation" aria-hidden="true"></span>
            @lang('PANOPTICON_APITOKENS_LBL_SECURITY_WARNING')
        </div>
    @else
        {{-- Token value (read-only, with copy button) --}}
        <div class="row mb-3">
            <label for="tokenValueDisplay" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_APITOKENS_LBL_TOKEN_VALUE')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="tokenValueDisplay"
                           value="{{{ $this->tokenValue }}}" readonly>
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('tokenValueDisplay').value)">
                        <span class="fa fa-copy" aria-hidden="true"></span>
                        @lang('PANOPTICON_APITOKENS_LBL_COPY')
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Description --}}
    <div class="row mb-3">
        <label for="description" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_APITOKENS_LBL_DESCRIPTION')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="description" id="description"
                   value="{{{ $model->description ?? '' }}}" maxlength="255">
        </div>
    </div>

    {{-- Expires --}}
    <div class="row mb-3">
        <label for="expires_at" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_APITOKENS_LBL_EXPIRES')
        </label>
        <div class="col-sm-9">
            <input type="datetime-local" class="form-control" name="expires_at" id="expires_at"
                   value="{{{ $defaultExpiry }}}"
                   placeholder="@lang('PANOPTICON_APITOKENS_LBL_EXPIRES_NEVER')">
        </div>
    </div>

    {{-- Enabled --}}
    <div class="row mb-3">
        <label for="enabled" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_APITOKENS_LBL_ENABLED')
        </label>
        <div class="col-sm-9">
            <div class="form-check form-switch">
                <input type="hidden" name="enabled" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       name="enabled" id="enabled" value="1"
                    {{ ($isNew || (int) ($model->enabled ?? 0) === 1) ? 'checked' : '' }}>
                <label class="form-check-label visually-hidden" for="enabled">@lang('PANOPTICON_APITOKENS_LBL_ENABLED')</label>
            </div>
        </div>
    </div>

    @unless ($isNew)
        {{-- Metadata (read-only) --}}
        <fieldset class="border rounded-1 p-3 mb-3">
            <legend class="float-none w-auto px-2 fs-6 text-muted">
                @lang('PANOPTICON_APITOKENS_LBL_METADATA')
            </legend>

            <div class="row mb-2">
                <div class="col-sm-3 fw-medium">@lang('PANOPTICON_APITOKENS_LBL_CREATED_ON')</div>
                <div class="col-sm-9">
                    @if (!empty($model->created_on) && $model->created_on !== '0000-00-00 00:00:00')
                        {{ $this->getContainer()->html->basic->date($model->created_on, $this->getLanguage()->text('DATE_FORMAT_LC2')) }}
                    @else
                        <span class="text-body-tertiary">@lang('PANOPTICON_APITOKENS_LBL_NEVER')</span>
                    @endif
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-sm-3 fw-medium">@lang('PANOPTICON_APITOKENS_LBL_CREATED_BY')</div>
                <div class="col-sm-9">
                    {{ $formatUserRef((int) ($model->created_by ?? 0)) }}
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-sm-3 fw-medium">@lang('PANOPTICON_APITOKENS_LBL_LAST_USED')</div>
                <div class="col-sm-9">
                    @if (!empty($model->last_used_at) && $model->last_used_at !== '0000-00-00 00:00:00')
                        {{ $this->getContainer()->html->basic->date($model->last_used_at, $this->getLanguage()->text('DATE_FORMAT_LC2')) }}
                    @else
                        <span class="text-body-tertiary">@lang('PANOPTICON_APITOKENS_LBL_NEVER')</span>
                    @endif
                </div>
            </div>

            @if ($lastUsedIpDisplay !== '')
                <div class="row mb-2">
                    <div class="col-sm-3 fw-medium">@lang('PANOPTICON_APITOKENS_LBL_LAST_USED_IP')</div>
                    <div class="col-sm-9 font-monospace">
                        {{{ $lastUsedIpDisplay }}}
                    </div>
                </div>
            @endif

            @if (!empty($model->modified_on) && $model->modified_on !== '0000-00-00 00:00:00')
                <div class="row mb-2">
                    <div class="col-sm-3 fw-medium">@lang('PANOPTICON_LBL_MODIFIED_ON')</div>
                    <div class="col-sm-9">
                        {{ $this->getContainer()->html->basic->date($model->modified_on, $this->getLanguage()->text('DATE_FORMAT_LC2')) }}
                    </div>
                </div>

                @if (!empty($model->modified_by))
                    <div class="row">
                        <div class="col-sm-3 fw-medium">@lang('PANOPTICON_LBL_MODIFIED_BY')</div>
                        <div class="col-sm-9">
                            {{ $formatUserRef((int) $model->modified_by) }}
                        </div>
                    </div>
                @endif
            @endif
        </fieldset>
    @endunless

    <input type="hidden" name="id" value="{{ (int) ($model->id ?? 0) }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
</form>
