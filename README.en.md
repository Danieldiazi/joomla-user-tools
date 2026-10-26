# Joomla User Tools

[Español](README.md) · **English**

Account administration tools for **Joomla 6**: compare a current user list, create accounts, review inactivity and schedule account blocking with reports.

| Tool | What it does |
|---|---|
| Compare a list | Proposes blocking accounts missing from the complete list, except protected accounts, and lets you create selected new users. |
| Review inactive users | Finds inactive accounts so you can select which ones to block. |
| Delete inactive users | Lets you select and permanently delete inactive accounts. By default, only blocked accounts are listed. Available since 2026.10.5. |
| Scheduled task | Blocks accounts for inactivity; starts in dry-run mode and generates reports. |
| Dashboard summary | Shows pending accounts, task status and notices on the Users dashboard. Available since 2026.10.6. |
| User groups on the site | Site module that lists the groups of a user or the members of a group. Read only. Available since 2026.10.7. |
| Groups and access levels | Read-only table of the access levels each group has, assigned or inherited. Available since 2026.10.8. |

**Blocking preserves the account and its data; deletion removes it permanently and cannot be undone.** Only the manual deletion tool deletes accounts. No tool automatically reactivates accounts.

**Requirements:** Joomla 6 and PHP 8.3 or later. Compatible with MySQL/MariaDB and PostgreSQL.  
**Download:** [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest), package `pkg_usertools-VERSION.zip`.  
**License:** [GPL-2.0-or-later](LICENSE).

## Contents

- [Installation and updates](#installation-and-updates)
- [Compare a user list](#compare-a-user-list)
- [Review inactive users](#review-inactive-users)
- [Delete inactive users](#delete-inactive-users)
- [Scheduled inactivity task and reports](#scheduled-inactivity-task)
- [Users dashboard summary](#users-dashboard-summary)
- [User groups module on the site](#user-groups-module-on-the-site)
- [Groups and access levels](#groups-and-access-levels)
- [Security](#security)
- [Options](#options)
- [Tool screenshots](#tool-screenshots)
- [Development](#development)
- [Architecture map](docs/architecture.en.md)
- [Building and publishing](#building-and-publishing)

For specific questions, see the [FAQ](FAQ.en.md). If you are contributing or working with a coding agent, read [AGENTS.md](AGENTS.md) (Spanish).

## Installation and updates

Download **pkg_usertools-VERSION.zip** from the [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest) and install it through System → Install → Extensions. The package installs the component, task plugin, “User Tools” administrator module and “User Tools - User Groups” site module. A fresh installation enables the “Task - User Tools” plugin; the first module installation publishes it on the Users dashboard. New tasks always start in dry-run mode.

Since 2026.10.3, Joomla notifies you of new versions under System → Update → Extensions, like any other extension: the package declares an update server pointing to the latest GitHub release. To update, install the new package over the existing installation; do not uninstall the component. Saved exclusions migrate automatically when updating from earlier versions.

The installer checks minimum versions, registers the extension with the User Actions Log and creates the “User Tools: account created” mail template. On uninstall, it warns that task reports remain in the logs directory.

## Compare a user list

**Example:** Joomla contains `ana`, `luis` and `marta`. If you paste a list containing `ana` and `marta`, the tool proposes blocking `luis`, unless protected or already blocked. If you add `pedro` and that account does not exist, you can select it for creation; it is not created automatically.

1. Open Components → User Tools → “Start comparison”.
2. Paste the **complete** list of current usernames, one per line, and review exclusions.
3. The review shows accounts to block, including name, email, last visit and groups, with a filter for long lists. It also shows usernames not found in Joomla, which you may choose to create.
4. Confirm. The result links each account to Joomla's user manager. To undo a block, open the account and set its status to Enabled.

Username comparison is case-insensitive: OTRO and otro are the same account. Empty lists are rejected. If **no username matches** an existing account, a red warning appears at the top of the review because all unprotected accounts would be proposed for blocking.

An incomplete list can cause incorrect blocks: always review the preview.

### Creating new accounts

New accounts are created in the **Registered** group, after checking that it exists and has no administrator access, with a different email address for each account. Choose between two modes for each operation:

- **Send a link to choose a password** (default). The account receives a random password that nobody sees, and the user receives an email linking to Joomla's standard password selection page. Edit the message under System → Mail Templates → “User Tools: account created”. Sending mail must be enabled in Global Configuration.
- **Show a temporary password.** A random password following Joomla's password policy (length and minimum digits, symbols, uppercase and lowercase letters) appears **once**, in the response that creates the account. Its clear text is not stored in the session, database or logs. The user must change it at first login.

The tool does not send the Joomla user plugin's welcome email.

## Review inactive users

On the home screen, choose “Review inactive accounts”. Select the number of days, whether to include users who have never logged in (using their registration date), and exclusions. Search, select only the accounts you want to suspend, and confirm. No accounts are initially selected, and “Select all” affects only rows visible through the filter. Already blocked accounts and accounts with recent sessions are skipped.

## Delete inactive users

On the home screen, choose “Review accounts for deletion”. This is the only tool that **deletes** accounts, and deletion cannot be undone; use the inactivity review to prevent access while preserving accounts.

1. Choose the number of days (730 by default), whether to include users who have never logged in, and which accounts to list: **blocked only** (default since 2026.10.6; accounts previously blocked by the review or scheduled task), **active and blocked**, or **active only**.
2. Configure this tool's own exclusions: administrators, usernames and groups (including child groups). Super Users and your own account are never listed.
3. The review shows name, email, status, last visit, registration date and groups, with a filter for long lists. No accounts are initially selected.
4. Select the accounts to delete, tick the confirmation box and **type the number of accounts**. This is always required, even for one account.

Every account is checked again before deletion. Accounts that changed after review (because they logged in, became excluded or have a recent session) are skipped and never replaced by others.

The period runs from the last visit or, when including users who have never logged in, from registration. **730 days is a fixed duration, not two calendar years.**

Deletion uses Joomla's User API, just like the user manager. Group memberships, profile data, sessions and private messages are removed; other extensions may clean up their own data. Articles and other content created by the user remain, as with Joomla's own deletion.

Each deletion is written to the **User Actions Log** with the account ID, username, name and operator (“… deleted the inactive account … with User Tools”). Joomla also adds its own user deletion entry.

## Scheduled inactivity task

1. Check that “Task - User Tools” is enabled; its status appears on the home screen.
2. Go to System → Scheduled Tasks → New and choose **User Tools: block inactive users**.
3. Configure the period (365 days by default), exclusions and safety limit.
4. **Dry-run mode is enabled by default.** Run it and review the report under Components → User Tools → Task results.
5. Once the candidates are correct, turn off dry-run mode and schedule the frequency; once a day is usually sufficient.

The last visit must be **strictly earlier** than the cutoff, calculated in UTC. The allowed period is 1–36,500 days. The task runs without a user identity: the visitor triggering Joomla's scheduler does not affect it.

### Safety limit against mass blocking

If a real run would block **more than the configured percentage of active accounts** (20% by default), it blocks none. The report shows “Stopped for safety” and the task ends with an error, allowing Joomla to send its usual failed-task notification. This protects against incorrect settings or last-visit dates blocking almost everyone.

The limit is checked only when a minimum number of candidates is reached (10 by default), so a few accounts on small sites do not stop the task. Dry-run mode only reports a warning.

With default settings, a site with 1,000 active accounts stops if more than 200 candidates are found. On a site with 30 active accounts, 7 candidates (23%) do not stop it because the minimum of 10 has not been reached. Setting the percentage to 0% or 100% disables the limit.

To run tasks without site visits, configure your hosting cron according to the [official Joomla guide](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### Task results

Components → User Tools → Task results shows each run with UTC date, task, mode, status and counts, with task, mode and status filters. Details list blocked, simulated, protected (with reasons) and failed accounts, and summarize omissions by reason. Each report can be **exported to CSV** (UTF-8, semicolon-separated, with spreadsheet formula protection).

Reports are stored in the usertools-reports subfolder of Joomla's logs directory, with a PHP header preventing direct web access. Old reports are deleted at the end of each run according to the retention days configured in Options.

## Users dashboard summary

Since 2026.10.6, the administrator **Users** dashboard (the dashboard icon next to “Users” in the sidebar) includes a “User Tools” module showing:

- **Unblocked inactive accounts**, using the period and exclusions of “Review inactive users”. The count links to that tool.
- **Blocked accounts pending deletion**: those offered by “Delete inactive users” with “Blocked only”, its period and exclusions. The count opens the tool with that option selected.
- **User Tools scheduled tasks**, including mode (dry run or real), last run, result (linked to its report) and next run.
- **Notices**: task plugin disabled or missing, no enabled task, a task stopped by the safety limit, a failed task or a report unfinished for more than an hour, and tasks still in dry-run mode more than 14 days after creation.

Counts use the same rules as the tools, so they match what you see when opening them. They are calculated at most every **10 minutes**, and recalculated as soon as a tool or task changes accounts, or Options are saved.

The module respects permissions: only users with access to User Tools can see it; counts require inactivity review or deletion permission, and tasks require task-results permission. Configure it like any administrator module (Content → Administrator Modules, “User Tools”): move it to another dashboard, rename or unpublish it. Updates do not reposition a moved or unpublished module.

The shared screenshots show the Spanish interface.

![User Tools module on the Users dashboard](docs/screenshots/13-panel-usuarios.jpg)

## User groups module on the site

Since 2026.10.7, the package includes the **“User Tools - User Groups”** site module. Joomla has no module that shows on the site which groups someone belongs to or who is in a group; this one does it in two modes. The installer does not publish it: create it in Content → Site Modules → New.

**Groups of a user.** Shows the groups of the *logged-in visitor* (each person sees their own; guests see nothing) or of *a specific user* chosen in the configuration. Options:

- *Include inherited groups*: adds the parent groups it inherits permissions from, dimmed. For example, a member of “Author” also sees “Registered”.
- *Groups not shown*: groups that never appear, such as “Public”.

**Members of a group.** Lists the active accounts of a group in alphabetical order. Options:

- *Include subgroups*: also lists people in the groups below the chosen one.
- *Show each member as*: name, username or both.
- *Hide accounts with administrator access* (on by default): does not list anyone in a group with administrator access or in Super Users, so the most sensitive accounts are not exposed on the site.
- *Maximum members* (50 by default, up to 1000) and *Show the member total*. When there are more members than the maximum, the module says how many are left.

In both modes, *Show when the list is empty* decides whether the module appears when there is nothing to list.

**Privacy.** The module never shows email addresses or blocked accounts, and it never changes accounts or groups. Even so, member names are personal data: use the module's **Access** field to decide who sees it (for example, *Registered*) instead of leaving it *Public*. For guests, the member list follows Joomla's module cache; for logged-in users it is always computed on the spot.

The list is read with a fixed number of queries, whatever the size of the group. Accounts with administrator access are decided per group, with Joomla's global permissions, as in the rest of User Tools.

## Groups and access levels

Since 2026.10.8, **Groups and access levels** shows in a single table which viewing access levels each user group has. Joomla only shows this relation from the levels side (Users → Access Levels, groups column), without inheritance and without a per-group view. This screen is read only: it never changes groups, levels or accounts.

- **Rows**: the groups, in their tree, with the number of active accounts assigned directly (linked to the user manager filtered by that group) and labels for Super Users, groups with administrator access and the guest group set in the Users Options.
- **Columns**: the levels, in their order. Each cell says whether the group has the level:
  - **Assigned**: the group is assigned to the level.
  - **Inherited**: one of its parent groups is, and it is named. Joomla grants the level to every group below an assigned group; for example, “Editor” has the *Registered* level because it sits below “Registered”.
  - **Always**: the *Public* level, which Joomla grants to everyone even when nobody is assigned.
  - **—**: the group does not have the level.
- **Summary by level**: assigned groups, how many groups have the level in total and how many accounts are assigned individually. A level with no groups or accounts is marked “No groups”, because nobody has it.

![Groups and access levels (Spanish interface)](docs/screenshots/14-grupos-niveles.jpg)

A search box filters the groups. Group and level titles link to their edit screens in Joomla for users with the Admin permission on Users (the same one Joomla requires for those screens), and account counts link to the user manager for users who can manage users. The screen requires its own permission, *View groups and access levels*: Super Users have it; grant it to others in Options → Permissions.

## Security

- **Super Users are always protected**, in every tool, even when “Exclude administrators” is disabled. User Tools does not block or delete Super User accounts.
- **The account performing a manual operation is always protected.**
- **Each block closes the account's open sessions**, as Joomla's user manager does (requires “Session Metadata”, enabled by default).
- **Creations, blocks and deletions are written to the User Actions Log** (Users → User Actions Log): who changed each account and which tool or scheduled task performed it.
- **Separate permissions**, delegable without granting Super User access (Options → Permissions):
  - *Compare list and block*, *Create accounts*, *Review inactive users*, *Delete inactive users*, *View task results* and *View groups and access levels*.
  - Non-Super Users can never block or delete accounts with administrator access.
- **Configurable reinforced confirmation**: from N accounts (10 by default), the exact number must be typed in addition to ticking the box.
- **Deletion always requires typing the number of accounts**, even for one; the configurable threshold does not disable this confirmation.
- **The site groups module is read only**: it never shows email addresses or blocked accounts, and by default it hides accounts with administrator access (see [groups module](#user-groups-module-on-the-site)).
- **Scheduled-task safety limit** against mass blocking (see [safety limit](#safety-limit-against-mass-blocking)).
- Single-use previews expire after 15 minutes; each account is revalidated immediately before saving; CSRF protection and non-cacheable pages are used.

## Options

Components → User Tools → **Options** (toolbar button):

| Tab | Settings |
|---|---|
| Security | Reinforced-confirmation threshold (0 disables it); default creation mode (email link or temporary password); report retention days (90 by default, 0 = forever) |
| Comparison | Exclude administrators, usernames and groups from manual comparison |
| Inactivity review | Period, include never-logged-in users, and manual review exclusions |
| Inactivity deletion | Period, account status (blocked only by default, both, or active only), include never-logged-in users, and deletion exclusions |
| Permissions | Permissions for each tool |

Manual-tool exclusions are also saved when using the tools. Each scheduled task has its own exclusions in its settings.

## Tool screenshots

These two screenshots were added with the deletion tool in 2026.10.5, on Joomla 6.2.0. They show the Spanish interface.

![Delete inactive users: period, account status and exclusions](docs/screenshots/11-eliminar-plazo.jpg)

![Delete inactive users: selection and confirmation](docs/screenshots/12-eliminar-seleccion.jpg)

See the [complete gallery](docs/screenshots.en.md) for the other tools; it includes screenshots from older versions.

## Development

See the [architecture map](docs/architecture.en.md) to follow flows, locate responsibilities, understand persistence and choose the relevant tests for each change.

Structure:

- `admin/`: component controllers, models, MVC views, templates and es-ES/en-GB language files.
- `admin/src/Service/`: domain logic shared with the plugin.
- `media/`: CSS and JS loaded through Web Asset Manager (`joomla.asset.json`).
- `plugins/task/usertools/`: task plugin.
- `modules/mod_usertools/`: Users dashboard summary module.
- `modules/mod_usertools_groups/`: site module that lists groups and members.
- `package/`: package manifest, installation script and languages.

Tests:

~~~sh
composer install && npm ci
vendor/bin/phpunit                    # service unit tests
npm test                              # JavaScript behaviour (jsdom)
JOOMLA_PATH=/path/to/joomla-6 vendor/bin/phpstan analyse   # static analysis, level 6
~~~

The end-to-end test `tests/integration/run.php` exercises the flows against a real disposable Joomla 6 site: comparison, emailed password setup through login, temporary passwords, delegated permissions, inactivity review and deletion, task safety limit, reports, CSV, action logging and the site groups module. See the file header for instructions; use `tests/integration/smtp-sink.php` as the test mail server.

GitHub Actions runs unit tests (PHP 8.3 and 8.4), JavaScript, PHPStan and end-to-end tests with PostgreSQL and MySQL on each push and pull request.

## Building and publishing

Versions use **year.month.revision**, without a leading zero in the month: 2026.10.0, 2026.10.1; the next month starts at 2026.11.0. Tags have a v prefix.

~~~sh
php scripts/build.php --output=dist                     # manifest version
php scripts/build.php --version=v2026.10.5 --output=dist
php tests/verify-build.php 2026.10.5 dist
~~~

Generates the five installable ZIPs (`pkg_usertools`, `com_usertools`, `plg_task_usertools`, `mod_usertools` and `mod_usertools_groups`), `SHA256SUMS.txt` and `updates.xml`. On Windows, `scripts\build.ps1` calls the same script.

To publish, create a GitHub release with a **vYYYY.M.REVISION** tag on `main` (Releases → Draft a new release → Choose a tag → Publish release). GitHub Actions runs tests, builds packages with that version in their manifests, and attaches them to the release with `SHA256SUMS.txt` and `updates.xml`. Existing installations receive updates through the update server. Prerelease suffixes are not supported.

## References

Independent implementation based on Joomla 6 APIs and the patterns of official task plugins such as [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Independent project with no official Joomla affiliation.
