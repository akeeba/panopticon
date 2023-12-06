## 🔎 Release highlights

🔣 This release is focused on improving the translation experience, and brings small improvements to the application.

**✨ Site notes** [gh-363]. You can now add notes to your sites. They are visible only to users who can edit the site. DO NOT STORE SENSITIVE INFORMATION, the notes are stored unencrypted.

**✏️ Translations now use PO files instead of a third party service**. The struggle is real, y'all! Hosted Weblate was great, but it seemed to be forgetting about the existence of some language strings in translated languages. Different strings in different languages. Alternatives were expensive, or we couldn't trust that their debatable definition of what constitutes FOSS deserving of free service form them would not change (we're not paranoid; it's happened before). Instead of trying to shoehorn our translations around the restrictions of proprietary services we decided to use standard PO files and FOSS to translate them, then use our own, custom-built FOSS to convert them to a format we can use. Openness all around; _we practice what we preach_. You can take a look in [our brand-new translation instructions](https://github.com/akeeba/panopticon/wiki/Translator-Resources) and start, or resume, translating now. 

**✏️ Light and Dark Mode for the TinyMCE and ACE editors**. We had this weird situation where the TinyMCE (HTML) editor was always in light mode, and the ACE (plain text) editor was always in dark mode, ensuring that _everyone_ was unhappy. Not any more! When your interface is in light mode, so are both editors. When your interface is in dark mode, so are both editors. Law and order has been restored across the land! 

**✏️ Improve login language selection**. The language selection in the login page "sticks" between user sessions, and is applied after logging into Panopticon as long as the logged-in user does not have an explicit language preference already set up.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an
  execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on
in [our issues tracker](https://github.com/akeeba/panopticon/issues).

Issues marked as _contemplating_ are those where we're still figuring out how to best implement in a way that makes
sense.

Issues marked as _planned_ are those which are being actively worked on, or queued up for implementation in the next
version.

Some issues may have been opened by third parties. Usually, they are relegated
to [Discussions](https://github.com/akeeba/panopticon/discussions), which is the best way to provide your feedback,
and/or engage in discussion about a new feature, improving an existing feature, or describing a behaviour you find
confusing. When there's something actionable in a discussion we will create a new issue with one of the aforementioned
tags, or with the _bug_ tag to indicate something that's broken and needs to be fixed.

Kindly remember that the order and timeframe for implementation largely depends on our available time, and our
assessment of expected complexity, and interdependencies between features. Security issues and bugs always take priority
over new features; there's no point polishing a broken glass. Thank you for your understanding!

## 📋 CHANGELOG

[//]: # (TODO)

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
