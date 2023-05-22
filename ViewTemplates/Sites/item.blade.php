<?php
/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$baseUri = \Awf\Uri\Uri::getInstance($this->item->getBaseUrl());
?>
<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->item->id }}</span>
    <span>{{{ $this->item->name }}}</span>
</h3>

<div class="text-end">
    <a href="{{{ $this->item->getBaseUrl() }}}" class="text-decoration-none">
        <span class="{{ ($baseUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $baseUri->getScheme() }}}://</span><span class="fw-medium">{{{ $baseUri->toString(['user', 'pass', 'host', 'port', 'path', 'query', 'fragment']) }}}</span>
        <span class="fa fa-external-link-alt fw-light text-muted small" aria-hidden="true"></span>
    </a>
</div>

<div class="container my-3">
    <div class="row row-cols-1 row-cols-lg-2 g-3">

        <div class="col">
            @include('Sites/item_joomlaupdate')
        </div>

        <div class="col">
            <div class="card">
                <h3 class="card-header h4">
                    <span class="fab fa-php" aria-hidden="true"></span>
                    PHP Version
                </h3>
                <div class="card-body">
                    Body
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <h3 class="card-header h4">
                    <span class="fa fa-cubes" aria-hidden="true"></span>
                    Extensions
                </h3>
                <div class="card-body">
                    Body
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <h3 class="card-header h4">
                    <span class="fa fa-hard-drive" aria-hidden="true"></span>
                    Backup
                </h3>
                <div class="card-body">
                    Body
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <h3 class="card-header h4">
                    <span class="fa fa-lock" aria-hidden="true"></span>
                    Security
                </h3>
                <div class="card-body">
                    Body
                </div>
            </div>
        </div>

    </div>
</div>