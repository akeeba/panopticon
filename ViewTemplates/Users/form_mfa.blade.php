<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Html\Html;
use Awf\Text\Text;
use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Users\Html $this */

$container = Factory::getContainer();
$user = $container->userManager->getUser($this->getModel()->getId());
$token = $container->session->getCsrfToken()->getValue();
?>

<div class="card">
    <div class="card-body">
        {{-- Card title --}}
        <h3 class="card-title mb-3">
            <span class="fa fa-user-lock" aria-hidden="true"></span>
            @lang('PANOPTICON_MFA_LBL_GUI_HEAD')
        </h3>

        {{-- What is this --}}
        <div class="form-text mb-2 p-2">
            @lang('PANOPTICON_MFA_LBL_DESCRIPTION')
        </div>

        {{-- MFA status --}}
        <div class="card card-body">
            <div class="d-flex flex-column flex-md-row gap-2 align-items-center">
                @if ($this->mfaActive)
                    <div class="flex-grow-1">
                        @sprintf('PANOPTICON_MFA_LBL_IS_ACTIVE', 'text-success fw-bold')
                    </div>
                    <div>
                        <a href="@route(sprintf(
                                'index.php?view=mfamethods&task=disable&user_id=%d&%s=1&returnurl=%s',
                                $user->getId(),
                                $token,
                                base64_encode(Uri::getInstance()->toString())
                            ))"
                           role="button" class="btn btn-danger">
                            <span class="fa fa-power-off" aria-hidden="true"></span>
                            @lang('PANOPTICON_MFA_LBL_TURN_OFF')
                        </a>
                    </div>
                @else
                    <div>
                        @sprintf('PANOPTICON_MFA_LBL_IS_DISABLED', 'text-danger fw-bold')
                    </div>
                @endif
            </div>
        </div>

        {{-- MFA list --}}
        <div class="my-3 p-2">
            @foreach($this->methods as $methodName => $method)
                    <?php
                    $defaultClass = $this->defaultMethod == $methodName ? 'border-2 border-warning bg-light-subtle' : '';
                    $defaultClass = $methodName === 'backupcodes' ? 'border-danger' : $defaultClass;
                    ?>
                <div class="border rounded-2 py-2 px-3 {{ $defaultClass }} mb-3">
                    {{-- MFA method header --}}
                    <div class="d-flex flex-row gap-2 align-items-center mb-3">
                        <img src="{{ Uri::root() . $method['image'] }}" alt="{{{ $method['name'] }}}"
                             class="img-fluid bg-light p-2 rounded-2" style="min-width: 3em; max-width: 7em">

                        <h4 class="m-0 p-0 flex-grow-1 fs-5">
                        <span>
                            {{ $method['display'] }}
                        </span>
                            @if($this->defaultMethod == $methodName)
                                <span class="fa fa-star text-warning" aria-hidden="true"></span>
                            @endif
                        </h4>

                        <span class="fa fa-info-circle" aria-hidden="true"
                              data-bs-toggle="tooltip" data-bs-placement="left"
                              data-bs-title="<?= $this->escape($method['shortinfo']) ?>"></span>
                    </div>

                    {{-- MFA active instances --}}
                    @if (count($method['active']))
                        <div class="my-2">
                            @foreach($method['active'] as $record)
                                <div class="ms-5 d-flex flex-column flex-md-row gap-3 align-items-center">
                                    {{-- Instance header --}}
                                    <div class="flex-grow-1 d-flex flex-column gap-1">
                                        @if ($methodName == 'backupcodes')
                                            <div class="p-2 mb-1">
                                                <span class="fa fa-info-circle" aria-hidden="true"></span>
                                                @sprintf(
                                                    'PANOPTICON_MFA_LBL_BACKUPCODES_PRINT_PROMPT',
                                                    $container->router->route(sprintf(
                                                        'index.php?view=mfamethod&task=edit&id=%d&returnurl=%s&user_id=%d',
                                                        (int) $record->id,
                                                        base64_encode(Uri::getInstance()->toString()),
                                                        $user->getId()
                                                    ))
                                                )
                                            </div>
                                        @else
                                            <div class="loginguard-methods-list-method-record-title-container">
                                                @if ($record->default)
                                                    <span class="fa fa-star text-body-tertiary" aria-hidden="true"
                                                          data-bs-toggle="tooltip" data-bs-placement="left"
                                                          data-bs-title="@lang('PANOPTICON_MFA_LBL_LIST_DEFAULTTAG')"></span>
                                                    <span class="visually-hidden">@lang('PANOPTICON_MFA_LBL_LIST_DEFAULTTAG')</span>
                                                    <span class="fw-semibold">
                                                    {{{ $record->title }}}
                                                </span>
                                                @else
                                                    {{{ $record->title }}}
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Last Used --}}
                                        <div class="d-flex flex-column flex-md-row align-items-center gap-3 text-muted">
                                        <span class="flex-grow-1">
                                            @sprintf(
                                            'PANOPTICON_MFA_LBL_CREATEDON',
                                            empty($record->created_on)
                                                ? '&mdash;'
                                                : $this->getContainer()->html->basic->date($record->created_on, Text::_('DATE_FORMAT_LC2'))
                                            )
                                        </span>
                                        <span class="flex-grow-1">
                                            @sprintf(
                                                'PANOPTICON_MFA_LBL_LASTUSED',
                                                empty($record->last_used)
                                                ? '&mdash;'
                                                : $this->getContainer()->html->basic->date($record->last_used, Text::_('DATE_FORMAT_LC2'))
                                            )
                                        </span>
                                        </div>
                                    </div>

                                    {{-- Buttons --}}
                                    @if ($methodName != 'backupcodes')
                                        <div>
                                            <a href="@route(sprintf(
                                        'index.php?view=mfamethod&task=edit&id=%s&returnurl=%s&user_id=%d',
                                        (int) $record->id,
                                        base64_encode(Uri::getInstance()->toString()),
                                        $user->getId()
                                    ))"
                                               role="button" class="btn btn-outline-primary">
                                                <span class="fa fa-pencil-alt" aria-hidden="true"></span>
                                                <span class="visually-hidden">
                                                @lang('PANOPTICON_MFA_LBL_EDIT')
                                            </span>
                                            </a>

                                            @if ($method['canDisable'])
                                                <a href="@route(sprintf(
                                        'index.php?view=mfamethod&task=delete&id=%s&returnurl=%s&user_id=%d&%s=1',
                                        (int) $record->id,
                                        base64_encode(Uri::getInstance()->toString()),
                                        $user->getId(),
                                        $token
                                    ))"
                                                   role="button" class="btn btn-outline-danger">
                                                    <span class="fa fa-trash-alt" aria-hidden="true"></span>
                                                    <span class="visually-hidden">
                                                    @lang('PANOPTICON_MFA_LBL_DELETE')
                                                </span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (empty($method['active']) || $method['allowMultiple'])
                        <div class="my-2">
                            <a href="@route(sprintf(
                            'index.php?view=mfamethod&task=add&method=%s&returnurl=%s&user_id=%d',
                            urlencode($method['name']),
                            base64_encode(Uri::getInstance()->toString()),
                            $user->getId()
                        ))"
                               role="button" class="btn btn-primary">
                                <span class="fa fa-plus-square" aria-hidden="true"></span>
                                @sprintf('PANOPTICON_MFA_LBL_LIST_ADD_A', $this->escape($method['display']))
                            </a>
                        </div>
                    @endif
                </div>

            @endforeach
        </div>

    </div>
</div>
