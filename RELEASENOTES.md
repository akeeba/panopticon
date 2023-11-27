## 🔎 Release highlights

🎅🏽 Ho, ho, ho! 🎄 The Akeeba Santa Claus(e) – see what we did there? – is coming early this year, carrying a bag full of feature updates and requested improvements to Akeeba Panopticon! Get your holiday season cheer on, and let's explore together the new features in version 1.0.5.

**✨ Detect stuck extension updates and allow rescheduling, or cancelling** [gh-304]. Panopticon can now detect that extension updates are stuck – if the task has been idle longer than the configured CRON stuck time (default: 3 minutes) with extension updates still in the queue – and will notify you. You can choose to either reschedule the updates, or cancel them altogether. In case you have deleted the site's extensions update task manually, effectively having the same effect of extension updates being scheduled without being able to install them, you will be notified and given the same options.

**✨ Allow immediate email sending [gh-306]**. In previous versions of Panopticon any email to be sent was added to the mail queue. We would trigger the sendmail feature every minute (as long as no other work was being done) to send these emails. This was meant as a protection against misconfigured mail servers which might time out, breaking the currently running task, or timeouts from slow mail servers when you have dozens of separate recipients. In most use cases this wasn't necessary, so now the emails are sent immediately. You can disable this feature to go back to using the safer enqueued emails.

**✨ Allow the global update preference of an extension to be "email" [gh-309]**. In the System Configuration page you could set the update preference for an individual extension only to global, none, patch, minor, or major. Now you can set it to email. Please note that it's recommended to use none and use the upcoming scheduled reports feature instead to minimize the amount of email you are receiving.

**✨ Detect when scheduled tasks are falling behind [gh-315]**. If the scheduled tasks are falling behind more than 2 minutes on average, and you are logged in as a Super User you will receive a warning recommending that you increase the number of CRON jobs you are using to execute scheduled tasks. This is linked to the documentation page of the CRON jobs, explaining why and how to do that.

**✨ Site configuration management CLI commands [gh-153]**. A new set of CLI commands has been added to help you list, set, and get the configuration of each site.

**✨ Collection and display of basic server information [gh-307]**. Panopticon collects some basic server health information and presents them to you, but it does not log them. The idea is to present you with a moment-in-time view of your server; it won't monitor the server itself. If you want server monitoring and/or uptime monitoring we strongly recommend using a third party service, such as [HetrixTools](https://hetrixtools.com/). 

**✨ Per-user language preference [gh-326]**. You asked, we delivered. Panopticon now allows each user to set their preferred language. The interface will appear in this language. Emails will be sent in this language, assuming mail templates exist for it. If a user has not selected a language the interface will appear in the language they have set up as their preferred in their browser, and emails will be sent in the default language you have set up in the System Configuration page. Mail templates can now be set up for a specific language, not just "All". Each user appears in the Users page with a small flag, and the (localised) language they prefer – as long as this preference is set. Finally, do note that the login page allows you to select a language, but this applied _only_ to the login screen.

**✨ Groups act as tags for site filtering [gh-333]**. Up until now, you could use Groups to set up advanced site access control, with different users having different view / admin permissions on different sites. As of this version, you can now filter all lists of sites and updates by one or more Groups, effectively having Groups perform double duty as tags. If you want to use Groups only as tags, just create Groups without giving them any privileges and without assigning users to any of these Groups. It's as simple as that!

**✨ Automatic API data sanitization** [gh-341]. The most common connection failure mode is a third party plugin soiling the API output either directly (outputting HTML), or by causing PHP to emit messages (warnings, notices, …) because the plugin, or Joomla! itself, are not fully compatible with newer PHP 8 versions. The thing is, once you get past the junk data in the response there's a perfectly usable JSON response we can use. Instead of failing outright, complaining the data is corrupt (which, technically, is true) we can attempt to clean up the data, which in most cases results in something perfectly usable. So, this is what we're doing in this version!

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

Issues marked as _contemplating_ are those where we're still figuring out how to best implement in a way that makes sense.

Issues marked as _planned_ are those which are being actively worked on, or queued up for implementation in the next version.

Some issues may have been opened by third parties. Usually, they are relegated to [Discussions](https://github.com/akeeba/panopticon/discussions), which is the best way to provide your feedback, and/or engage in discussion about a new feature, improving an existing feature, or describing a behaviour you find confusing. When there's something actionable in a discussion we will create a new issue with one of the aforementioned tags, or with the _bug_ tag to indicate something that's broken and needs to be fixed.

Kindly remember that the order and timeframe for implementation largely depends on our available time, and our assessment of expected complexity, and interdependencies between features. Security issues and bugs always take priority over new features; there's no point polishing a broken glass. Thank you for your understanding!

## 📋 CHANGELOG

[//]: # (TODO)

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
