# Mail Templates

## Who receives email?

Email messages which have a system-wide (Panopticon installation) scope are only sent to users with the `Super` privilege.

Email messages concerning a site are sent to all users with the `Super` and `Manage` privileges. Moreover, they are sent to the users you have chose to CC, as set up in the site's configuration.

## Common CSS

Sending HTML-formatted emails can be tricky. Inline CSS styles will be stripped away by most email clients. Using tables for everything is a pain, and very limited in what you can do.

To work around those problems, Panopticon can insert CSS styles within the `<head>` element of HTML formatted email. Since the visual editor only lets you edit the `<body>` content of HTML formatted email the CSS to be added has to be edited separately.

Go to Mail Templates and click on Common CSS in the toolbar to edit the CSS which is added to all HTML-formatted email messages.

The Common CSS is also loaded automatically into the mail template editor. Therefore, what you see is (mostly) what you get. Please keep in mind that most email clients support a subset of the CSS syntax.

## Mail Template Types

Panopticon knows about a number of mail templates. Each one of them is sent on a different occasion.

You can manage the mail templates in the Mail Templates page.

If a mail template is missing the corresponding email is not sent.

Every mail template has a Subject, HTML Content, and Plain Text Content. The Subject is, of course, the subject line of the email message sent. The emails are sent as both HTML formatted email and as plain text email. The HTML Content of a mail template is the HTML-formatted version of the email, whereas the Plain Text Content is the plain text (not formatted text) version. They are defined separately because they are expected to differ ever so slightly in content.

All three content fields —Subject, HTML Content, and Plain Text Content— support _variables_. The variables are in the form `[SOME_THING]` (uppercase string enclosed in square brackets). They are replaced before the email is sent.

All mail types support the following variables:

* `[NAME]` The name of the user receiving the email. In case of an additional email set up on a site's preferences, the email address of the recipient is used instead.
* `[EMAIL]` The email address of the recipient.
* `[URL]` The URL to your Akeeba Panopticon installation

### Joomla!™ core update

These email templates are sent in response to Akeeba Panopticon detecting or attempting to install a new Joomla! version on your site.

The following variables are available for all Joomla! core update mail templates:

* `[SITE]` The name of the site related to the Joomla! core update, as defined in Akeeba Panopticon
* `[SITE_URL]` The URL to the site related to the Joomla! core update, inferred from the Endpoint URL set up in Akeeba Panopticon.
* `[NEW_VERSION]` The new version of Joomla! which was or will be installed.
* `[OLD_VERSION]` The old version of Joomla! which will be or has been replaced.

#### Joomla Core Update: Update found

Key: `joomlaupdate_found`

Sent when Akeeba Panopticon detects that there is a new Joomla! version available on a site, _but_ it is set up in a way which prevents its automatic installation.

Use this mail type to notify the user that they will need to install the new Joomla! version manually.

#### Joomla Core Update: Update will be installed

Key: `joomlaupdate_will_install`

> This email type is _NOT_ set up out of the box, i.e. Akeeba Panopticon will not send this email. You can set it up yourself.

Sent when Akeeba Panopticon detects that there is a new Joomla! version available on a site, and it has queued the site for automatic installation of the new version.

Use this mail type to notify the user that the site will be updated automatically.

#### Joomla Core Update: Update installed

Key: `joomlaupdate_installed`

Sent after Akeeba Panopticon has installed a new Joomla! on a site.

Use this mail type to notify the user that the site has been updated.

#### Joomla Core Update: Update failed

Key: `joomlaupdate_failed`

Sent when Akeeba Panopticon attempted to install a new Joomla! version on a site, but it failed to do so.

Use this mail type to notify the user that the site upgrade has failed. They need to check if the site is functional and upgrade it manually.

Additional variables available:

* `[MESSAGE]` The error message received during the (failed) update attempt.