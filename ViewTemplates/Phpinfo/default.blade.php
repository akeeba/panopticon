@if(empty($this->phpInfo))
    <h3 class="display-6 text-center bg-light p-2 fw-bold">
        <span class="fab fa-php me-2" aria-hidden="true"></span>
        PHP Version {{ PHP_VERSION }}
    </h3>
    <div class="alert alert-danger">
        <h3 class="alert-heading">
            <code>phpinfo()</code> is not available on your server
        </h3>
        <p>
            Your server operator has disabled the built-in <code>phpinfo()</code> PHP function. Panopticon cannot display detailed PHP information on your server.
        </p>
    </div>
@else
    {{ $this->phpInfo }}
@endif