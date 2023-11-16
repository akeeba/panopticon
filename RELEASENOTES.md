## 🔎 Release highlights

This is a features, and bug-fix release.

**✨ Site reports (updates, backups, file scanner, Admin Tools actions) [gh-220]**. Panopticon now logs when it has found and installed updates, taken backups, ran the PHP File Change Scanner, and executed Admin Tools actions on your sites. You can generate reports for a specific period of time (default: one month) and site. This is great if you want to show your clients that there is maintenance taking place on their sites.

**✨ Support for custom templates [gh-249]**. If customising the CSS is not enough for your white-labelling needs you can now create a custom template. This lets you change the logos, the footer and so on.

**✨ Send test email [gh-267]**. Getting the email configured on any site can be a pain, right? You will never know if you got the settings right unless you can send a test email. There's a new button in the System Configuration page to do exactly that.

**✨ Major performance improvement for scheduled tasks**. The task runner used to pick up a task, run it, then wait for 5 seconds – even if the task itself took half a second. This made lengthy processes like updating lots of sites, backing up sites etc take much longer than they should. The task runner will now check for new tasks right after the previous one is done, as long as we're not within 5 seconds of the execution time limit. If no tasks are available, _only then_ it will wait for five seconds before checking for new tasks. This is a major performance boost. You will find that site updates that used to take ten minutes will now complete in a minute or less.

**✨ Extension list search box [gh-247]**. Wherever there is a list of extensions you can now type into a search box to filter them by extension and author name. This will make finding the right extension faster and easier, especially on sites with _tons_ of extensions.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 📋 CHANGELOG

✨ Site reports (updates, backups, file scanner, Admin Tools actions) [gh-220]
✨ Support for custom templates [gh-249]
✨ Send test email [gh-267]
✨ Major performance improvement for scheduled tasks
✨ Extension list search box [gh-247]
✂️ Removed avatars
🐞 ➖ Repeated notifications for updates if more than one extension with updates is found [gh-258]
🐞 ➖ Cannot access My Profile page [gh-241]
🐞 ➖ PHP error in the Extensions Updates page if you have extensions with missing Download Keys [gh-240]
🐞 🔺 Post-update code does not apply database changes [gh-283]

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)