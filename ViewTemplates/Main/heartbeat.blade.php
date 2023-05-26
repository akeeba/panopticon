<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

?>
<div class="alert alert-danger my-3 d-none" id="heartbeatWarning">
    <h3 class="alert-heading">
        <span class="fa fa-heart-broken" aria-hidden="true"></span>
        Automated tasks appear to be broken
    </h3>
    <p>
        It is more than a minute since the last time an automated task run. You need to set up CRON jobs to run every minute.
    </p>
    <p>
        <a class="btn btn-info" href="@route('index.php?view=main&layout=cron')">
            <span class="fa fa-book-open pe-2" aria-hidden="true"></span>
            Read the instructions
        </a>
    </p>
</div>