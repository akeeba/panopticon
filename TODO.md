# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview

## CRON jobs

Describe how to set up

You can have more than one CRON jobs. You need one CRON job for every ~100 sites you manage. If any site take more than 10 seconds to respond, you need one CRON job for every ~25 sites you manage. CRON jobs have a maximum memory utilisation in the area of 20MiB with minimal CPU utilisation.

## Automated tasks and when things run

Explain the types of system tasks and their frequency:

* `logrotate` Daily
* `refreshsiteinfo` Every 15'
* `refreshinstalledextensions` Every 15'
* `joomlaupdatedirector` Every 3'
* `extensionupdatesdirector` Every 10'
* `sendmail` Every minute

You cannot disable them. If they are disabled, visiting the main page of Panopticon will re-enable them. 

Each site can have one or more of these run-once task types:

* `joomlaupdate` Created by system task `joomlaupdatedirector`, or when you click the button to update / refresh your Joomla installation.
* `extensionsupdate` Created by system task `extensionupdatesdirector`, or when click the button to update an extension.

After they finish running they are automatically disabled. If they get stuck, you can delete them, and they will be created afresh.

You can see last run time and status of all tasks in the Tasks page.

## Users, Groups, and Permissions

Panopticon uses a very simple permissions system, inspired by the traditional UNIX model for filesystem permissions. If you understand how file permissions work, you already have an intuitive grasp on how Panopticon's permissions work.

### Users

Users control who can log into Panopticon and what they can do. Every user can belong to zero or more Groups.

Every user can be given zero or more of the following Permissions which apply system-wide:

* **Superuser**. Gives access to application-level configuration pages, and automatically grants all other permissions. You need at least one User account with this permission. Try to keep the number of users with Superuser privileges to a minimum and only use them for configuration and maintenance of the system.
* **Administration**. Allows editing the configuration of a Site.
* **View**. Allows viewing the Site Overview page of a Site.
* **Execute**. Allows executing actions (scheduling updates, taking backups, …) on a Site.
* **Add Own**. Allows adding new sites, owned by the User. The user will need to have the View or Edit Own privilege to view the Site Overview page of their own sites. The user will need to have Administration or Edit Own privilege to edit their own sites. The user will need the Execute or Edit Own privilege to execute actions on their own sites.
* **Edit Own**. Grants the user the Administration, View, and Execute privileges only on sites which are owned by the user, i.e. sites where the Created By matches this user.

To create self-service users grant them the Add Own and Edit Own privileges.

Remember that user-level privileges granted to a user override the privileges granted to a user by their Groups membership. Think of privileges as keys. If someone has a key that matches a lock, they can open that lock. The Superuser key is the main skeleton key which opens all locks on all sites. The Administration, View, and Execute are skeleton keys which work on the respective lock on all sites. The Add Own and Edit Own are skeleton keys which only work on the locks of the sites owned by the user.

### Groups

Groups only carry a name and zero or more of the following permissions:

* **View**
* **Execute**
* **Administration**

These are the same permissions as the ones you saw for each User. However, they do NOT apply across all sites. They only apply to the sites which belong to that Group, as long as the user trying to access that site also belongs in that Group. 

The idea is that Group privileges grant users privileges they don't have at the User (global) level, for specific sites. This allows you for fine-grained control of site access.

### Putting it all together.

It's easier to have an example.

We have three users:
* `root` with the Superuser privilege selected. Does not belong to any Group. 
* `secretary` with no privilege selected. Belongs to the Secretarial group.
* `operator` with no privilege selected. Belongs to the Operators group.
* `admin` with no privilege selected. Belongs to the Secretarial, Operators, and Admins group.

We have three groups:
* `Secretarial` with the View privilege selected.
* `Operators` with the Execute privilege selected.
* `Admins` with the Administration privilege selected.

We have two sites:
* `Main Site`, belongs to the Secretarial, Operators, and Admins group.
* `Super Secret Site`, does not belong to any group.

The `root` user can view both sites. They can also edit these sites' configurations, and execute updates on them.

The `admin` user can only see the `Main Site`. They can also edit this one site's configuration, and execute updates on it.

The `operator` user can only see the `Main Site`. They can also execute updates on it, but not edit its configuration.

The `secretary` user can only see the `Main Site`. They can neither edit its configuration, nor execute updates on it.

**Your takeaway**: By creating appropriate groups and assigning them to your users and sites, you can give your clients' staff access to the Panopticon pages for their sites. You are not exposing your other clients' sites to them - Panopticon remains mum about these other sites to these users. By varying the per-group privileges you can prevent your clients from carrying out potentially problematic operations on their sites in Panopticon.

## Why no uptime monitoring

Does not make sense from a single location

Monitoring works best if you can also monitor the server's operating parameters

You can use [HetrixTools](https://hetrixtools.com), (UptimeRobot)[https://uptimerobot.com], or any similar service.
