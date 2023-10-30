## 🔎 Release highlights

This is a **security**, features, and bug-fix release.

**🚨 Security update (critical): Non-Super Users can edit or remove any other user, including Super Users**. An inversion of logic in the access control code allows non-super-users to manage users without being hindered in any way. This issue has been addressed with improved logic.

**✨ TOTP Multi-factor Authentication (gh-168)**. You can now use Time-based One-Time Password (e.g. Google Authenticator) as a Multi-factor Authentication method for your Panopticon user account.

**✨ Extensions Update Overview page (gh-178)**. You can view extension updates across all of your sites, and schedule (some of) them for installation _en masse_.

**✨ Core Updates Overview page (gh-178)**. You can view available CMS updates across all of your sites, and schedule (some of) them for installation _en masse_.

**✨ Automated task to check for self-updates (gh-174)**. Self-updates are no longer checked only when you log into Panopticon itself. They will be checked automatically every 6 hours and an email will be sent to Super Users to let them know the first time an updated version is found. Installing Panopticon updates _IS NOT_ automatic; you will have to do initiate it, at a time that is most convenient for you.

**✨ Take a backup before updating Joomla! (gh-16)**. You can now tell Panopticon to take a backup of your site before updating Joomla!. This feature requires Akeeba Backup Professional version 7 or later to be installed on your site.

**✏️ Improve behavior clicking Edit without selecting a site**. You will be taken back to the list page with a message telling you that you cannot do that, instead of receiving an error page (red page).

**✏️ Improve the MFA method selection layout**. That page no longer looks like it was designed in 1998. Sorry about that, we forgot to include the CSS last time out…

**✏️ Caching tweaks**. We identified a number of cases where the caching failed to work, including the caching of updates. These have been fixed. Moreover, various single-purpose caches are now moved under the `cache/system` folder for consistency.

## 🚨 Security advisory

We recommend that all users install this version as soon as possible. Versions 1.0.0 and 1.0.1 have a critical vulnerability as described above.

The vulnerability affects all Panopticon installations with non-Super Users. If you cannot update immediately, we recommend that you disable access to all users except Super Users on your Panopticon installations.

Note: Super Users can edit any other user, including other Super Users. This is the expected and desired behaviour.

## ℹ️ Information about the updates

Because of caching issues in versions 1.0.0 and 1.0.1 you may not see the new, updated versions.

Please delete the contents of the `cache/self_update` folder and go back to Panopticon's main page. You should now see the updated version.

This issue has been addressed with the caching tweaks explained above.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 📋 CHANGELOG

Changes in version 1.0.2

* 🚨 Security [critical]: non-Super Users can change or remove other users, including Super Users
* ✨ TOTP Multi-factor Authentication (gh-168)
* ✨ Extensions Update Overview page (gh-178)
* ✨ Core Updates Overview page (gh-178)
* ✨ Automated task to check for self-updates (gh-174)
* ✨ Take a backup before updating Joomla! (gh-16)
* ✏️ Improve behavior clicking Edit without selecting a site
* ✏️ Improve the MFA method selection layout
* ✏️ Caching tweaks
* 🐞 [LOW] Missing email template type for failed Joomla! update
* 🐞 [LOW] Invalid extensions could cause PHP errors listing a site's extensions

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix