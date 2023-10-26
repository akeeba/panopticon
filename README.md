# Akeeba Panopticon

Self-hosted site monitoring and management


[![Documentation](https://img.shields.io/badge/documentation-wiki-ffffff?labelColor=514f50&color=40b5b8)](https://github.com/akeeba/panopticon/wiki) &nbsp; [![Downloads](https://img.shields.io/github/downloads/akeeba/panopticon/total?labelColor=514f50&color=40b5b8)](https://github.com/akeeba/panopticon/releases) &nbsp; [![Translations on Weblate](https://img.shields.io/badge/Weblate-Translations-40b5b8?labelColor=514f50)](https://hosted.weblate.org/projects/akeeba-panopticon/)

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

## Translations

🚧 **WORK IN PROGRESS** 🚧 – The translation environment is currently being provisioned.

Akeeba Panopticon uses Weblate (https://weblate.org) for managing its translations. Anyone can become a translator by joining the [public translation project](https://hosted.weblate.org/projects/akeeba-panopticon/) there. 

Translations are _community–led_ and licensed under the GNU Affero General Public License version 3, or (at your option) any later version.

Community–led translations means that we have no responsibility for the contents of the offered translations, including the terminology used, spelling and grammar errors, and accuracy. The only officially supported language of Akeeba Panopticon is English (Great Britain).

## License Notice

Akeeba Panopticon – Self-hosted site monitoring and management

Copyright (C) 2023  Akeeba Ltd

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received [a copy of the GNU Affero General Public License](LICENSE.txt) along with this program.  If not, see <https://www.gnu.org/licenses/>.
