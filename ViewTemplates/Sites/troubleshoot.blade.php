<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');
?>
<div class="card my-3 border-info">
    <h3 class="card-header bg-info text-white">
        <span class="fa fa-bug-slash"></span>
        Troubleshooting Information
    </h3>
    <div class="card-body bg-info-subtle">
        @if($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBlocked::class)
            <p class="fw-semibold">
                The Joomla! API Application is blocked (HTTP error 403)
            </p>
            <p>
                Something on your server —such as the server configuration controlled by your host, a server configuration file such as <code>.htaccess</code>, or a plugin of your site— is blocking Panopticon from accessing your site's API application (<span class="font-monospace">{{{ $this->item->url }}}</span>).
            </p>
            <p>
                Things to check:
            </p>
            <ul>
                <li>
                    Does your host, or your server configuration file (e.g. <code>.htaccess</code>), block access to the Joomla! API Application endpoint (<span class="font-monospace">{{{ $this->item->url }}}</span>) altogether?
                </li>
                <li>
                    Does your host have a server–side protection solution (such as Apache's mod_security2) which might be blocking the Joomla! API Application endpoint (<span class="font-monospace">{{{ $this->item->url }}}</span>), your API token (<span class="font-monospace">{{{ $config->get('config.apiKey') }}}</span>), or the User-Agent of Panopticon (<span class="font-monospace">panopticon/{{{ AKEEBA_PANOPTICON_VERSION }}}</span>)?
                </li>
                <li>
                    Do you have a security, SEO, or other similar plugin which may be blocking the above?
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBroken::class)
            <p class="fw-semibold">
                The Joomla! API Application responded with an unexpected HTTP status code ({{{ $this->httpCode }}})
            </p>
            @if ($this->httpCode > 500)
                <p>
                    The HTTP status code {{{ $this->httpCode }}} means there has been a server error. Check if there are any third party plugins, especially system plugins, which may be accidentally breaking the Joomla! API Application and contact their author.
                </p>
            @elseif($this->httpCode === 400)
                <p>
                    The HTTP status code 400 usually means that your site can only be accessed by a limited number of domain names; the one you tried using (<span class="font-monospace">{{{ (new \Awf\Uri\Uri($this->item->url))->getHost() }}}</span>) may not be one of them.
                </p>
                <p>
                    If you are using Admin Tools Professional please go to your site's backend and click on Components, Admin Tools for Joomla!, Web Application Firewall, Configure WAF, Request Filtering. Check the list of <em>Allowed Domains</em> towards the bottom of the page.
                </p>
                <p>
                    If this was not the case, please contact your host.
                </p>
            @elseif($this->httpCode === 401)
                <p>Joomla! believes that your API Token is incorrect, corrupt, or otherwise cannot log you in.</p>
                <p>
                    Make sure that the API Token you are using belongs to a user account which belongs to the Super User group and the user is enabled. Do note that asking for a password reset disables the user temporarily. Do not trust your recollection, or your assumptions; double-check the user on your site.
                </p>
                <p>
                    Make sure that the plugins “API Authentication - Web Services Joomla Token” and “User - Joomla API Token” are enabled on your site.
                </p>
                <p>
                    It is also possible that something on your server, or a plugin on your site, is corrupting the API Token as it is transmitted to your site.</p>
                <p>
                    Please check your installed Joomla! plugins. Kindly note that no, Admin Tools Professional for Joomla! <em>does not</em> interfere with the Joomla! API Token. If this does not help please contact your host.
                </p>
            @elseif($this->httpCode === 406)
                <p>
                    The HTTP status code 406 (Not Acceptable) means that Joomla! does not see our HTTP <code>Accept</code> header, or a plugin on your site modified it in a way which resulted in an error.
                </p>
                <p>
                    Please check that there are no <code>system</code> or <code>webservices</code> plugins which may be corrupting the HTTP Accept header.
                </p>
                <p>
                    If this does not help, please contact your host and ask them to check if the Accept header is forwarded correctly to PHP. It should be <code>Accept: application/vnd.api+json</code>.
                </p>
            @else
                <p>
                    Most likely a <code>system</code> or <code>webservices</code> plugin on your server modified the request we sent, resulting in an error. Please check your installed plugins.
                </p>
            @endif
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\APIInvalidCredentials::class)
            <p class="fw-semibold">
                Joomla! believes that your API Token is incorrect, corrupt, or otherwise cannot log you in.
            </p>
            <p>
                Make sure that the API Token you are using belongs to a user account which belongs to the Super User group and the user is enabled. Do note that asking for a password reset disables the user temporarily. Do not trust your recollection, or your assumptions; double-check the user on your site.
            </p>
            <p>
                Make sure that the plugins “API Authentication - Web Services Joomla Token” and “User - Joomla API Token” are enabled on your site.
            </p>
            <p>
                It is also possible that something on your server, or a plugin on your site, is corrupting the API Token as it is transmitted to your site.</p>
            <p>
                Please check your installed Joomla! plugins. Kindly note that no, Admin Tools Professional for Joomla! <em>does not</em> interfere with the Joomla! API Token. If this does not help please contact your host.
            </p>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\cURLError::class)
            <p class="fw-semibold">
                There was a communication error with your site.
            </p>
            <p>
                The communication library used by PHP (cURL) has reported the following error:
            </p>
            <p class="px-2 text-secondary">
                {{{ $this->curlError }}}
            </p>
            <p>
                Please consult the <a href="https://curl.se/libcurl/c/libcurl-errors.html" target="_blank">cURL library error code reference</a> for more information about the error. In most cases the problem is a network or server configuration issue —either the server you have installed Panopticon on, or the server your Joomla! site is on— which requires contacting the respective host to help you address.
            </p>
        @elseif($this->connectionError === \GuzzleHttp\Exception\GuzzleException::class)
            <p class="fw-semibold">
                There was a communication error with your site.
            </p>
            <p>
                The communication library used by Panopticon (<a href="https://github.com/guzzle/guzzle" target="_blank">Guzzle HTTP</a>) has reported the following error:
            </p>
            <p class="px-2 text-secondary">
                {{{ $this->curlError }}}
            </p>
            <p>
                This is an unexpected condition which may indicate a communication error we have not yet seen, a problem within Guzzle itself, a problem with the server you have installed Panopticon on, or a problem in Panopticon itself. Please search the open <em>and closed</em> issues <a href="https://github.com/akeeba/panopticon/issues?q=is%3Aissue" target="_blank">on our GitHub repository</a>. If nobody else has reported this, please open an issue and we will take a look.
            </p>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName::class)
            <p class="fw-semibold">
                The host name of your site (<span class="font-monospace">{{{ (new \Awf\Uri\Uri($this->item->url))->getHost() }}}</span>) cannot be resolved to an IP address.
            </p>
            <p>
                Please check the following:
            </p>
            <ul>
                <li>
                    Have you typed the URL correctly? Check carefully, misspellings are easy to make and hard to catch!
                </li>
                <li>
                    Have you recently bought the domain, assigned the domain to a server, or transferred your site between hosts? Keep in mind that DNS propagation takes anywhere from 5 minutes to several days. Moreover, due to the distributed nature of DNS, it is possible that you can see your site <em>from your device</em> but the server you have installed Panopticon on can't.
                </li>
                <li>
                    If your site's domain resolves to an IPv6 address —but not an IPv4 address— make sure that the server you have installed Panopticon on supports IPv6. You may have to ask your host to confirm that this is the case.
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\PanopticonConnectorNotEnabled::class)
            <p class="fw-semibold">
                The Akeeba Panopticon Connector is not enabled on your site
            </p>
            <p>
                Your Joomla! site needs to have the <a href="https://github.com/akeeba/panopticon-connector/releases/latest" target="_blank">Akeeba Panopticon Connector</a> installed and enabled for Akeeba Panopticon to work with it.
            </p>
            <p>
                Please check the following:
            </p>
            <ul>
                <li>
                    Have you installed the Akeeba Panopticon Connector package on your site?
                </li>
                <li>
                    Go to System, Manage, Extensions. Is the “Akeeba Panopticon Connector” <em>component</em> installed and enabled?
                </li>
                <li>
                    Go to System, Manage, Extensions. Is the “Akeeba Panopticon Connector” <em>package</em> installed and enabled?
                </li>
                <li>
                    Go to System, Manage, Plugins. Is the “Web Services - Panopticon” plugin enabled?
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL::class)
            <p class="fw-semibold">
                You are using a self-signed SSL/TLS certificate
            </p>
            <p>
                You are using HTTPS to access your site, but the SSL/TLS certificate you are using is not signed by a commercial, well-known Certification Authority.
            </p>
            <p>
                You can address this issue by doing <em>one</em> of the following:
            </p>
            <ul>
                <li>
                    Use plain HTTP. This is the least secure option, therefore strongly not recommended.
                </li>
                <li>
                    Install an SSL/TLS certificate signed by a commercial, well-known Certification Authority. Most servers allow you to use <a href="https://en.wikipedia.org/wiki/Let%27s_Encrypt" target="_blank">Let's Encrypt</a> which issues valid, signed, fully working SSL/TLS certificates free of charge.
                </li>
                <li>
                    If you are on an Intranet, extranet, or otherwise need to maintain your own Certification Authority you can tell Panopticon to recognise your custom Certification Authority. Create the file <code>cacert.pem</code> inside the <code>user_code</code> folder of your Panopticon installation. Copy and paste your Certification Authority's (CA) public certificate(s) in <a href="https://en.wikipedia.org/wiki/Privacy-Enhanced_Mail" target="_blank">PEM</a> format. This is loaded <em>on top of</em> the standard CA files automatically, allowing Panopticon to access your site.
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem::class)
            <p class="fw-semibold">
                Your site's SSL/TLS certificate has a problem
            </p>
            <p>
                You are using HTTPS to access your site, but the SSL/TLS certificate you are using appears to have a problem.
            </p>
            <p>
                Please check the following:
            </p>
            <ul>
                <li>
                    Is the SSL/TLS certificate for the right domain name? If you are using the default SSL/TLS certificate provided by your host it is limited to the temporary domain name you use to set up your site, not your real domain name. You will need to install an SSL/TLS certificate signed by a commercial, well-known Certification Authority. Most servers allow you to use <a href="https://en.wikipedia.org/wiki/Let%27s_Encrypt" target="_blank">Let's Encrypt</a> which issues valid, signed, fully working SSL/TLS certificates free of charge. Remember that some self-service hosting control panels (such as cPanel, or Plesk) allow you to select which is the active SSL/TLS certificate for a domain name. Do check that the correct certificate is, indeed, being used.
                </li>
                <li>
                    Has the SSL/TLS certificate expired? In this case you need to renew it. Please note that if you are using Let's Encrypt the certificate has a short validity period (usually 90 days) and it should be renewed automatically by your host. If this did not happen, you will need to contact your host.
                </li>
                <li>
                    Has the SSL/TLS certificate been revoked? Sometimes Certification Authorities, or the owners of an SSL/TLS certificate, may choose to revoke it. For example, Certification Authorities will do that if they suspect fraud, or if they believe their infrastructure used to issue a certificate was compromised. <a href="https://www.namecheap.com/support/knowledgebase/article.aspx/9968/38/how-to-check-the-certificate-revocation-status/" target="_blank">Check your certificate's revocation status</a>. If it's been revoked you will need a new certificate.
                </li>
                <li>
                    Is it possible that your server serves the wrong SSL/TLS certificate? This may happen if you have recently switched to a new certificate, either manually or as part of an automated certificate rotation process (e.g. when your host issues a new certificate through Let's Encrypt). When the certificate changes the web server needs to be reloaded or restarted. In some rare cases this might not happen — or an inexperienced systems administrator may forget to do it. Please contact your host / systems administrator to check that and, if necessary, reload or restart the web server process.
                </li>
            </ul>
        @elseif($this->connectionError === \Akeeba\Panopticon\Exception\SiteConnection\WebServicesInstallerNotEnabled::class)
            <p class="fw-semibold">
                The “Web Services - Installer” plugin is disabled on your site
            </p>
            <p>
                Panopticon uses the Joomla! API Application services provided by the “Web Services - Installer” plugin to ensure that all necessary extensions are installed and published on your site.
            </p>
            <p>
                Please go to your site's backend and click on System, Manage, Extensions. Search for the “Web Services - Installer” plugin and enable it.
            </p>
        @else
            <p class="fw-semibold">
                Uh oh. Something is totally broken here…
            </p>
            <p>
                Normally we can detect the nature of the connection error and provide some useful troubleshooting information here, or at least point you to some kind of useful, third party troubleshooting resource. When this is not the case, we can at least print out an error message which will help us help you troubleshoot it.
            </p>
            <p>
                Instead, we got an error which we should be able to provide troubleshooting information for (<span class="font-monospace">{{{ $this->connectionError }}}</span>), but we don't have the code for it. This should have not happened.
            </p>
            <p>
                Please delete all files and folders inside the <code>tmp</code> folder of Akeeba Panopticon. This addresses the problem of this page's cached copy being severely out of date. If this doesn't help, please let us know.
            </p>
        @endif
    </div>
</div>