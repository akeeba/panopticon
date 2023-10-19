# Akeeba Panopticon

Self-hosted site monitoring and management

[Documentation](https://github.com/akeeba/panopticon/wiki) • [Downloads](https://github.com/akeeba/panopticon/releases)

## What is it?

Akeeba Panopticon is a standalone PHP application. You install on a domain or subdomain separate from your sites. You can then use it to monitor manage your sites remotely.

Currently, it supports Joomla 3, Joomla 4, and Joomla 5 sites.

## Quick start

The recommended installation method for most users is the [public release ZIP file](https://github.com/akeeba/panopticon/releases).

If you want to contribute to Panopticon, or would rather install by cloning the Git repository, we have detailed instructions [in the documentation](https://github.com/akeeba/panopticon/wiki/Install-from-Git). If you're in a hurry, there's a short version below:

Make sure you have `git`, `composer`, and `npm` (Node.js 16 or later) in your path.

Clone the repository.

Go into the folder where you cloned the repository and run `composer install`. This is important! This step installs PHP dependencies, runs `npm install`, compiles SCSS files into CSS, minifies JavaScript files, puts all external static resources in the folder Panopticon expects them to be, and generates a few necessary files (such as `version.php`).

You can now either use the [web installer](https://github.com/akeeba/panopticon/wiki/Install-Panopticon) or the [CLI installation](https://github.com/akeeba/panopticon/wiki/CLI-setup) method.