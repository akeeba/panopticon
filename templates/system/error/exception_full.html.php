<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

?>
<!-- <?= $_message = sprintf('%s (%d %s)', $exceptionMessage, $statusCode, $statusText); ?> -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="<?= $this->charset; ?>" />
        <meta name="robots" content="noindex,nofollow" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?= $_message; ?></title>
        <link rel="icon" type="image/png" href="media/images/logo_colour.svg">
        <style><?= $this->include('assets/css/exception.css'); ?></style>
        <style><?= $this->include('assets/css/exception_full.css'); ?></style>
        <style>
            :root {
                --font-sans-serif: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
                --font-monospace: "Berkeley Mono", "Consolas", "Monaco", "Menlo", "Ubuntu Mono", "Liberation Mono", monospace;
                --page-background: #fcfcfc;
                --color-text: #373637 ;
                --color-success: #79a638;
                --color-warning: #ec971f ;
                --color-error: #c81d23;
                --color-muted: #6b686a ;
                --tab-background: #fff;
                --tab-color: #514f50;
                --tab-active-background: #373637;
                --tab-active-color: #fcfcfc;
                --tab-disabled-background: #d6d6d6;
                --tab-disabled-color: #6b686a;
                --metric-value-background: #fff;
                --metric-value-color: inherit;
                --metric-unit-color: #d6d6d6;
                --metric-label-background: #e0e0e0;
                --metric-label-color: inherit;
                --table-border: #efefef;
                --table-background: #fff;
                --table-header: #efefef;
                --trace-selected-background: #ffd9a2;
                --tree-active-background: #ffd9a2;
                --exception-title-color: var(--base-2);
                --shadow: 0px 0px 1px rgba(128, 128, 128, .2);
                --border: 1px solid #efefef;
                --background-error: var(--color-error);
                --highlight-comment: #6b686a;
                --highlight-default: #373637;
                --highlight-keyword: #c81d23;
                --highlight-string: #339092;
                --base-0: #fff;
                --base-1: #fcfcfc;
                --base-2: #efefef;
                --base-3: #d6d6d6;
                --base-4: #6b686a;
                --base-5: #514f50;
                --base-6: #373637;
            }

            header {background-color: #514f50}
            code,pre{font-family: var(--font-monospace)}
            p.info {padding: .5em .75em;border:1px solid #339092;background-color: #339092;color:white;border-radius: .5em;font-size:1rem;line-height: 1.7}

            @media print {
                ul.tab-navigation{display:none}
                div[id*=tab-].hidden {display:block}
                div#tab-0-0{display:none}
            }
        </style>
    </head>
    <body>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const toggleDarkMode = (dark) => {
                    document.body.classList.remove('theme-dark');

                    if (dark) {
                        document.body.classList.add('theme-dark');
                    }
                };

                window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", e => toggleDarkMode(e.matches));

                toggleDarkMode(window.matchMedia("(prefers-color-scheme: dark)").matches)
            });
        </script>

        <header>
            <div class="container">
                <h1 class="logo"><?= $this->include(APATH_MEDIA . '/images/logo_colour.svg') ?> Panopticon Exception</h1>

                <div class="help-link">
                    <a href="https://github.com/akeeba/panopticon/wiki">
                        <span class="icon"><?= $this->include('assets/images/icon-book.svg'); ?></span>
                        <span class="hidden-xs-down">Panopticon</span> Docs
                    </a>
                </div>
            </div>
        </header>
        <?= $this->include('views/exception.html.php', $context); ?>

        <script>
            <?= $this->include('assets/js/exception.js'); ?>
        </script>
    </body>
</html>
<!-- <?= $_message; ?> -->
