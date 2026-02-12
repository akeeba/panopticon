<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * @var \Akeeba\Panopticon\View\Mailtemplates\Html $this
 * @var array                                      $RESULTS
 * @var int                                        $SUCCESS_COUNT
 * @var int                                        $FAIL_COUNT
 * @var int                                        $DISABLED_COUNT
 * @var int                                        $TOTAL_COUNT
 */

defined('AKEEBA') || die;

$RESULTS = is_array($RESULTS) ? $RESULTS : (array) $RESULTS;

?>
<!-- Main-Topic -->
<div class="akemail-main-topic">
    <p>
        Mass extension installation has completed.
        Out of {{ $TOTAL_COUNT }} sites: {{ $SUCCESS_COUNT }} succeeded, {{ $FAIL_COUNT }} failed, {{ $DISABLED_COUNT }} had remote installation disabled.
    </p>
</div>
<!-- Message -->
<div class="akemail-message">
    <table style="border-collapse: collapse; width: 100%; margin: 1em 0;">
        <thead>
        <tr style="background: #f0f0f0;">
            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Site</th>
            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Status</th>
            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Message</th>
        </tr>
        </thead>
        <tbody>
        @foreach($RESULTS as $siteId => $result)
            <?php
            $result   = (array) $result;
            $siteName = $result['site_name'] ?? 'Site #' . $siteId;
            $status   = $result['status'] ?? 'unknown';
            $message  = $result['message'] ?? '';
            $color    = match($status) {
                'success'  => '#28a745',
                'disabled' => '#ffc107',
                default    => '#dc3545',
            };
            $label = match($status) {
                'success'  => 'Success',
                'disabled' => 'Disabled',
                'failed'   => 'Failed',
                default    => ucfirst($status),
            };
            ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">{{{ $siteName }}}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">
                    <span style="color: {{ $color }}; font-weight: bold;">{{ $label }}</span>
                </td>
                <td style="border: 1px solid #ddd; padding: 8px;">{{{ $message }}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
