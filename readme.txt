=== BlogLogistics Site Management Tools ===
Contributors: bloglogistics
Tags: site management, managed sites, mainwp, admin tools, access protection
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.1.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protects BlogLogistics managed-site access, including the BlogLogistics admin account, MainWP Child connector, and inactive BlogLogistics plugin update visibility.

== Description ==

BlogLogistics Site Management Tools protects the core access components used to manage BlogLogistics client sites.

The plugin protects the BlogLogistics administrator account and the MainWP Child connector from normal WordPress admin screens where another administrator could accidentally remove, deactivate, or alter managed-site access.

It also provides a safe update bridge for inactive official BlogLogistics plugins. Inactive plugins cannot run their own updater code, so this plugin checks a single approved BlogLogistics plugin index and adds update offers only for installed inactive plugins listed there.

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
* Uses the BlogLogistics update manifest endpoint for its own updates.
* Checks a single approved BlogLogistics plugin index for inactive plugin update support.
* Adds update offers for installed inactive official BlogLogistics plugins only when they are listed in the approved index.
* Does not guess manifest URLs and does not probe one-off BlogLogistics plugins.
* Uses GitHub release assets for plugin ZIP downloads.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate the plugin in WordPress.
3. Confirm the BlogLogistics account remains available for managed-site access.
4. Confirm MainWP Child remains connected.

== Usage ==

After activation, the plugin works automatically.

When logged in as the BlogLogistics management account, protected tools remain visible and manageable.

When logged in as another administrator, protected management infrastructure is hidden from normal WordPress admin screens where practical.

Inactive official BlogLogistics plugins that are listed in the approved BlogLogistics plugin index can still show update offers in WordPress even though those inactive plugins cannot run their own updater code.

== Frequently Asked Questions ==

= Does this remove the BlogLogistics admin account? =

No. The plugin protects the BlogLogistics admin account. It does not remove it.

= Does this recreate the BlogLogistics account if someone deletes it? =

No. This version does not recreate accounts. It focuses on preventing accidental deletion or role changes from normal WordPress admin workflows.

= Does this hide MainWP Child from every possible place? =

No plugin can hide another plugin from filesystem access, database access, hosting control panels, backups, security scanners, or WP-CLI. This plugin hides and protects MainWP Child from normal WordPress admin screens where practical.

= Why is MainWP Child protected? =

MainWP Child is part of the BlogLogistics managed-site connection. Accidentally deactivating or deleting it can break remote site management.

= Who can still see protected management tools? =

The BlogLogistics management account can still see and manage protected tools.

= Does this scan every plugin with BlogLogistics in the name? =

No. It checks only installed inactive plugins that are listed in the approved BlogLogistics plugin index at updates.bloglogistics.com. It does not guess manifest URLs and it ignores one-off BlogLogistics plugins that are not listed in the index.

= Does this force active BlogLogistics plugins to update faster? =

No. Active plugins continue to use their own updater code and normal WordPress update behaviour.

== Changelog ==

= 1.1.2 =
* Add BlogLogistics plugin icon assets and update manifest icon metadata.
* Pass manifest icon metadata through inactive plugin update offers and details modals.

= 1.1.1 =
* Fix the version details modal for inactive official BlogLogistics plugin updates.
* Show the plugin details and changelog tabs instead of a Plugin not found message.

= 1.1.0 =
* Add update support for installed inactive official BlogLogistics plugins using an approved plugin index.
* Check only the plugin manifest URLs listed in the BlogLogistics plugin index.
* Ignore one-off BlogLogistics plugins that are not listed in the index.
* Avoid guessed manifest URLs and avoid needless 404 requests.
* Keep active BlogLogistics plugins on their own updater logic.

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

= 1.1.2 =
Adds BlogLogistics plugin icon support for update screens and details modals.

= 1.1.1 =
Fixes the version details modal for inactive official BlogLogistics plugin updates.

= 1.1.0 =
Adds update visibility for installed inactive official BlogLogistics plugins that are listed in the approved BlogLogistics plugin index.

== License ==

This plugin is licensed under GPL-3.0-or-later.
See https://www.gnu.org/licenses/gpl-3.0.html.
