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
Mass extension installation has completed.
Out of {{ $TOTAL_COUNT }} sites: {{ $SUCCESS_COUNT }} succeeded, {{ $FAIL_COUNT }} failed, {{ $DISABLED_COUNT }} had remote installation disabled.

===========================================================================

@foreach($RESULTS as $siteId => $result)
<?php
$result   = (array) $result;
$siteName = $result['site_name'] ?? 'Site #' . $siteId;
$status   = $result['status'] ?? 'unknown';
$message  = $result['message'] ?? '';
$label    = match($status) {
    'success'  => 'SUCCESS',
    'disabled' => 'DISABLED',
    'failed'   => 'FAILED',
    default    => strtoupper($status),
};
?>
[{{ $label }}] {{{ $siteName }}}
@if (!empty($message))
  {{ $message }}
@endif

@endforeach
