<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Dbtools\Html $this
 */

$token      = $this->getContainer()->session->getCsrfToken()->getValue();
$totalFiles = 0;
$totalSize  = 0;
?>

<div class="alert alert-info">
    <h3 class="alert-heading h6">
        @lang('PANOPTICON_DBTOOLS_LBL_INFO_HEAD')
    </h3>
    <p class="mb-0">
        @lang('PANOPTICON_DBTOOLS_LBL_INFO_BODY')
    </p>
</div>


<table class="table table-striped table-hover">
    <thead>
    <tr>
        <th>
            @lang('PANOPTICON_DBTOOLS_LBL_FILENAME')
        </th>
        <th>
            @lang('PANOPTICON_LBL_FIELD_CREATED_ON')
        </th>
        <th>
            @lang('PANOPTICON_DBTOOLS_LBL_SIZE')
        </th>
        <th>
            @lang('PANOPTICON_DBTOOLS_LBL_ACTIONS')
        </th>
    </tr>
    </thead>
    <tbody>
    @foreach($this->files as $item)
    <?php
        $totalFiles++;
		$totalSize += $item->size;
    ?>
    <tr>
        <td>
            @if (str_ends_with($item->filename, '.sql.gz'))
                <span class="fa fa-fw fa-file-archive" aria-hidden="true"
                      data-bs-tooltip="tooltip" data-bs-placement="bottom"
                      data-bs-title="@lang('PANOPTICON_DBTOOLS_LBL_FILETYPE_GZ')"
                ></span>
                <span class="visually-hidden">
                    @lang('PANOPTICON_DBTOOLS_LBL_FILETYPE_GZ')
                </span>
            @else
                <span class="fa fa-fw fa-file-code" aria-hidden="true"
                      data-bs-tooltip="tooltip" data-bs-placement="bottom"
                      data-bs-title="@lang('PANOPTICON_DBTOOLS_LBL_FILETYPE_SQL')"
                ></span>
                <span class="visually-hidden">
                    @lang('PANOPTICON_DBTOOLS_LBL_FILETYPE_SQL')
                </span>
            @endif
            <code>{{{ $item->filename }}}</code>
        </td>
        <td>
            {{ $this->getContainer()->html->basic->date($item->ctime->format(DATE_ATOM), $this->getLanguage()->text('DATE_FORMAT_LC6')) }}
        </td>
        <td>
            {{  $this->formatFilesize($item->size) }}
        </td>
        <td>
            <div class="d-flex flex-column flex-lg-row gap-2 align-items-center justify-content-evenly">
                <a href="@route(sprintf('index.php?view=dbtools&task=download&file=%s&%s=1&format=raw', urlencode($item->filename), $token))"
                   class="btn btn-primary btn-sm"
                   data-bs-tooltip="tooltip" data-bs-placement="bottom"
                   data-bs-title="@lang('PANOPTICON_DBTOOLS_LBL_DOWNLOAD')"
                   download="download"
                >
                    <span class="fa fa-fw fa-file-download" aria-hidden="true"></span>
                    <span class="visually-hidden">
                        @sprintf('PANOPTICON_DBTOOLS_LBL_DOWNLOAD_SR', $this->escape($item->filename))
                    </span>
                </a>

                <a href="@route(sprintf('index.php?view=dbtools&task=delete&file=%s&%s=1', urlencode($item->filename), $token))"
                   class="btn btn-danger btn-sm"
                   data-bs-tooltip="tooltip" data-bs-placement="bottom"
                   data-bs-title="@lang('PANOPTICON_DBTOOLS_LBL_DELETE')"
                >
                    <span class="fa fa-fw fa-trash-alt" aria-hidden="true"></span>
                    <span class="visually-hidden">
                        @sprintf('PANOPTICON_DBTOOLS_LBL_DELETE_SR', $this->escape($item->filename))
                    </span>
                </a>
            </div>
        </td>
    </tr>
    @endforeach
    </tbody>
    <tfoot class="table-group-divider">
    <tr>
        <td colspan="2" class="fw-medium text-info">
            @plural('PANOPTICON_DBTOOLS_LBL_FILES_N', $totalFiles)
        </td>
        <td class="fw-medium text-info">
            {{ $this->formatFilesize($totalSize) }}
        </td>
        <td>
            <div class="d-flex flex-column gap-2 align-items-center justify-content-evenly">
                <a href="@route(sprintf('index.php?view=dbtools&task=startBackup&%s=1', $token))"
                   class="btn btn-outline-primary">
                    <span class="fa fa-fw fa-play-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_DBTOOLS_LBL_BACKUP_NOW')
                </a>
            </div>
        </td>
    </tr>
    </tfoot>
</table>
