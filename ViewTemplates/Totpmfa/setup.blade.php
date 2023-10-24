<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Task\Status;
use Awf\Html\Select;
use Awf\Registry\Registry;
use Awf\Html\Html as HtmlHelper;
use Awf\Text\Text;

defined('AKEEBA') || die;

/**
 * @var \Awf\Mvc\DataView\Html $this
 */

$svg = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $svg);
?>

<div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
        <div class="card">
            <p class="h3 card-header">
                Set up your authenticator software
            </p>
            <div class="card-body d-flex flex-column flex-lg-row gap-3 gap-lg-0 align-items-start justify-content-center">
                {{ $svg }}
                <div>
                    <p>
                        Depending on your authenticator software, do one of the following:
                    </p>
                    <ul>
                        <li>
                            Scan this QR Code&trade;.
                        </li>
                        <li>
                            Use this <a href="{{ $uri }}">link</a>.
                        </li>
                        <li>
                            Enter the Secret <code>{{ $secret }}</code>
                        </li>
                    </ul>
                    <p>
                        Enter the generated 6-digit code into the field below, and click on “Save”.
                    </p>
                    <p class="text-info small">
                        <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
                        Looking for compatible authenticator software? We have tested with <a href="https://keepassxc.org/">KeePassXC</a>, <a href="https://strongboxsafe.com/">Strongbox</a>, <a href="https://1password.com/">1Password</a>, <a href="https://en.wikipedia.org/wiki/Google_Authenticator">Google Authenticator</a>, <a href="https://authy.com/">Twilio Authy</a>, and the built-in feature in macOS / iOS / iPadOS.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>