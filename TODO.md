# Documentation notes

Users: show language under full name.

Test multi-language mail sending.

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview

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
