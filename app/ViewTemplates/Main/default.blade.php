<?php
defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 */
?>
<table class="table table-striped">
    <thead>
    <tr valign="middle">
        <th>#</th>
        <th>Site</th>
        <th>
            <span class="fab fa-joomla fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="Joomla™ version"
            ></span>
            <span class="visually-hidden">
            Joomla™ version
            </span>
        </th>
        <th>
            <span class="fa fa-cubes fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="Extensions status"
            ></span>
            <span class="visually-hidden">
            Extensions status
            </span>
        </th>
        <th>
            <span class="fab fa-php fs-3" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="PHP version"
            ></span>
            <span class="visually-hidden">
            PHP version
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
                {{ $item->id }}
            </td>
            <td>
                <div class="fw-medium">
                    {{ $item->name }}
                </div>
                <div class="small">
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