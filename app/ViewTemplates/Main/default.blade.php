<?php
defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 */
?>
<table class="table table-striped">
    <caption class="visually-hidden">
        @lang('PANOPTICON_MAIN_SITES_TABLE_CAPTION')
    </caption>
    <thead>
    <tr valign="middle">
        <th>@lang('PANOPTICON_MAIN_SITES_THEAD_SITE')</th>
        <th>
            <span class="fab fa-joomla fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PANOPTICON_MAIN_SITES_THEAD_JOOMLA')"
            ></span>
            <span class="visually-hidden">
            @lang('PANOPTICON_MAIN_SITES_THEAD_JOOMLA')
            </span>
        </th>
        <th>
            <span class="fa fa-cubes fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PANOPTICON_MAIN_SITES_THEAD_EXTENSIONS')"
            ></span>
            <span class="visually-hidden">
            @lang('PANOPTICON_MAIN_SITES_THEAD_EXTENSIONS')
            </span>
        </th>
        <th>
            <span class="fab fa-php fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PHP version')"
            ></span>
            <span class="visually-hidden">
            @lang('PHP version')
            </span>
        </th>
    </tr>
    </thead>
    <tbody>
    <?php
    /** @var \Akeeba\Panopticon\Model\Site $item */
    ?>
    @foreach($this->items as $item)
        <?php
        $url    = substr($item->url, 0, strrpos($item->url, '/api'));
        $config = new Awf\Registry\Registry($item->config);
        ?>
        <tr>
            <td>
                <div class="fw-medium">
                    {{ $item->name }}
                </div>
                <div class="small">
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                    <a href="{{{ $url }}}" class="link-secondary text-decoration-none" target="_blank">
                        {{{ $url }}}
                        <span class="fa fa-external-link-square"></span>
                    </a>
                </div>
            </td>
            <td>
                @include('main/site_joomla', [
	                'item' => $item,
	                'config' => $config,
                ])
            </td>
            <td>
                @include('main/site_extensions', [
	                'item' => $item,
	                'config' => $config,
                ])
            </td>
            <td>
                @include('main/site_php', [
	                'item' => $item,
	                'config' => $config,
	                'php' => $config->get('core.php')
                ])
            </td>
        </tr>
    @endforeach
    </tbody>
</table>