=== BlogLogistics Site Management Tools ===
Contributors: bloglogistics
Tags: site management, managed sites, mainwp, admin tools, access protection
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protects BlogLogistics managed-site access, including the BlogLogistics admin account and MainWP Child connector.

== Description ==

BlogLogistics Site Management Tools protects the core access components used to manage BlogLogistics client sites.

The plugin protects the BlogLogistics administrator account and the MainWP Child connector from normal WordPress admin screens where another administrator could accidentally remove, deactivate, or alter managed-site access.

This plugin is intended for BlogLogistics-managed WordPress sites where the BlogLogistics account and MainWP Child connector are part of the site management setup.

== Features ==

* Protects the bloglogistics administrator account from normal admin visibility for other users.
* Prevents non-BlogLogistics administrators from deleting the BlogLogistics management account.
* Restores the administrator role if another administrator attempts to downgrade the BlogLogistics management account.
* Hides MainWP Child from normal plugin lists for users other than the BlogLogistics account.
* Removes deactivate, delete, and edit action links for protected plugins where practical.
* Reduces accidental bulk deactivation or deletion risk from plugin screens.
* Hides MainWP Child menu entries from users other than the BlogLogistics account.
* Hides this guard plugin from normal plugin lists for users other than the BlogLogistics account.
* Uses a plugin-specific GitHub updater wrapper to avoid conflicts with other BlogLogistics plugins.
* Uses GitHub release-based updates with automated WordPress ZIP builds.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate the plugin in WordPress.
3. Confirm the BlogLogistics account remains available for managed-site access.
4. Confirm MainWP Child remains connected.

== Usage ==

After activation, the plugin works automatically.

When logged in as the BlogLogistics management account, protected tools remain visible and manageable.

When logged in as another administrator, protected management infrastructure is hidden from normal WordPress admin screens where practical.

== Frequently Asked Questions ==

= Does this remove the BlogLogistics admin account? =

No. The plugin protects the BlogLogistics admin account. It does not remove it.

= Does this recreate the BlogLogistics account if someone deletes it? =

No. This version does not recreate accounts. It focuses on preventing accidental deletion or role changes from normal WordPress admin workflows.

= Why is MainWP Child protected? =

MainWP Child is part of the BlogLogistics managed-site connection. Accidentally deactivating or deleting it can break remote site management.

= Who can still see protected management tools? =

The BlogLogistics management account can still see and manage protected tools.

== Changelog ==

= 1.0.4 =
* Generate the update manifest changelog from readme.txt so WordPress displays the full changelog.

= 1.0.3 =
* Automate update manifest generation and upload from GitHub Actions.

= 1.0.2 =
* Switch update checks to the BlogLogistics update manifest endpoint.
* Avoid GitHub API update checks to reduce rate-limit errors.

= 1.0.1 =
* Prevent Plugin Update Checker from loading more than once when multiple BlogLogistics plugins are active.
* Keep updater wrapper class plugin-specific to avoid conflicts with other BlogLogistics plugins.

= 1.0.0 =
* Rename plugin to BlogLogistics Site Management Tools.
* Standardize plugin for GitHub release-based updates.
* Add plugin-specific GitHub updater integration.
* Add automated WordPress ZIP build workflow.
* Update requirements to WordPress 7.0 and PHP 8.3.
* Protect the BlogLogistics admin account from normal admin visibility and accidental deletion.
* Protect MainWP Child from accidental deactivation or deletion through normal WordPress admin screens.

== Upgrade Notice ==

= 1.0.0 =
Renamed and standardized as BlogLogistics Site Management Tools. This is a new plugin slug and should be installed carefully alongside or in place of the previous BlogLogistics Maintenance Access plugin.

== License ==

This plugin is licensed under GPL-3.0-or-later.
See https://www.gnu.org/licenses/gpl-3.0.html.
