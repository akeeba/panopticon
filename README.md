# Akeeba Panopticon

Self-hosted site monitoring and management

[![Documentation](https://img.shields.io/badge/documentation-wiki-ffffff?labelColor=514f50&color=40b5b8)](https://github.com/akeeba/panopticon/wiki) &nbsp; [![Downloads](https://img.shields.io/github/downloads/akeeba/panopticon/total?labelColor=514f50&color=40b5b8)](https://github.com/akeeba/panopticon/releases)`

## What is it?

Akeeba Panopticon is a standalone PHP application. You install on a domain or subdomain separate from your sites. You can then use it to monitor and manage your sites remotely.

Currently, it supports Joomla and WordPress sites. Each CMS requires a connector extension to be installed on the monitored site:

* [Panopticon Connector for Joomla 3](https://github.com/akeeba/panopticon_connector_j3) — Joomla 3.9 and 3.10 only
* [Panopticon Connector for Joomla](https://github.com/akeeba/panopticon-connector) — Joomla 4.x, 5.x, and 6.x
* [Panopticon Connector for WordPress](https://github.com/akeeba/panopticon-connector-wordpress) — WordPress 5.x and 6.x

## Quick start

The recommended installation method for most users is the [public release ZIP file](https://github.com/akeeba/panopticon/releases) or the [official container image (Docker, Podman, etc)](https://github.com/akeeba/panopticon/wiki/Using-Docker).

## Running from source

If you want to contribute to Panopticon or would rather install it by cloning the Git repository, we have detailed instructions [in the documentation](https://github.com/akeeba/panopticon/wiki/Install-from-Git). If you're in a hurry, there's a short version below:

Make sure you have `git`, `composer`, and `npm` (Node.js 16 or later) in your path.

Clone the repository.

Go into the folder where you cloned the repository and run `composer install`. This is important! This step installs PHP dependencies, runs `npm install`, compiles SCSS files into CSS, minifies JavaScript files, puts all external static resources in the folder Panopticon expects them to be, and generates a few necessary files (such as `version.php`).

You can now either use the [web installer](https://github.com/akeeba/panopticon/wiki/Install-Panopticon) or the [CLI installation](https://github.com/akeeba/panopticon/wiki/CLI-setup) method.

## Translations

Translations are _community–led_ and licensed under the GNU Affero General Public License version 3, or (at your option) any later version.

Community–led translations mean that we have no responsibility for the contents of the offered translations, including the terminology used, spelling and grammar errors, and accuracy. The only officially supported language of Akeeba Panopticon is English (Great Britain).

If you are interested in translating Panopticon, please check out the [translator resources page](https://github.com/akeeba/panopticon/wiki/Translator-Resources).

## License Notice

Akeeba Panopticon – Self-hosted site monitoring and management

Copyright (C) 2023-2026 Akeeba Ltd

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received [a copy of the GNU Affero General Public License](LICENSE.txt) along with this program.  If not, see <https://www.gnu.org/licenses/>.

## Statement on the use of AI

Our stance on AI is that it's a useful tool, but it needs oversight by an experienced developer – much like what you'd expect of a very enthusiastic junior developer.

We use generative AI to process and improve our documentation, assist us with release-adjacent tasks such as authoring release notes, and as a coding (**NOT** software archtiecture!) agent. The output of generative AI is _always_ reviewed by an experienced human developer who signs off the commit and is responsible for it.

We do not discourage the use of AI when you submit issues and Pull Requests. However, we will hold you to the same standard we hold ourselves. Please do review your AI-assisted submission to avoid "AI slop". Thank you for your understanding!