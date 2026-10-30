# Joomla User Tools

[Español](README.md) · **English**

Account administration tools for **Joomla 6**: compare a current user list, create accounts, review inactivity and schedule account blocking with reports.

| Tool | What it does |
|---|---|
| Compare a list | Proposes blocking accounts missing from the complete list, except protected accounts, and lets you create selected new users. |
| Review inactive users | Finds inactive accounts so you can select which ones to block. |
| Anonymise accounts | Replaces the personal data of the accounts in a list, of every inactive account or of a group, without deleting them. Permanent, with a review and a typed number. Available since 2026.10.17. |
| Delete inactive users | Lets you select and permanently delete inactive accounts. By default, only blocked accounts are listed. Available since 2026.10.5. |
| Scheduled task | Blocks accounts for inactivity; starts in dry-run mode and generates reports. |
| Dashboard summary | Shows pending accounts, task status and notices on the Users dashboard. Available since 2026.10.6. |
| User groups on the site | Site module that lists the groups of a user or the members of a group. Read only. Available since 2026.10.9. |
| Add accounts to groups | Adds existing accounts to groups by ID or full path without removing current memberships, with review and confirmation. |
| Remove from groups | Choose an account, untick the groups it should leave and, if you want, also remove it from all their subgroups. With a review and a confirmation. Available since 2026.10.9. |
| Move groups | Changes the parent of several groups at once, with their subgroups, after reviewing which levels their members gain or lose. Refuses moves that would change administrator access. Super Users only. Available since 2026.10.16. |
| Groups and access levels | Read-only table of the access levels each group has, assigned or inherited. Available since 2026.10.9. |
| Content by access level | How many articles, categories, menu items, modules, fields and other items use each level, with a link to each filtered list. Read only. Available since 2026.10.12. |
| Content by age | Table by year with how many articles were last modified in each one, filterable by access level; each number opens the list of those articles with a link to edit them. Read only. Available since 2026.10.15. |
| Access diagnosis | Points out accounts with administrator access and no activity, a single remaining Super User, access levels nobody has, content with levels that do not exist and groups with no members. It explains each notice and is read only. Available since 2026.10.15. |

**Blocking preserves the account and its data; deletion removes it permanently and cannot be undone.** Only the manual deletion tool deletes accounts. No tool automatically reactivates accounts.

The home screen groups the tools into four sections, each with its explanation always visible: **Review accounts** (compare a list and review inactive users), **Groups and access levels** (remove from groups, the read-only screens about groups, levels and content, and the access diagnosis), **Automation and follow-up** (the scheduled task, with its plugin status, and its results) and, last and on its own, **Delete accounts**, the only one marked “Permanent”. Each person only sees the tools they are allowed to use, and sections with none are left out. From any other screen, the «User Tools home» button in Joomla's toolbar goes back to the home screen.

**Requirements:** Joomla 6 and PHP 8.3 or later. Compatible with MySQL/MariaDB and PostgreSQL.  
**Download:** [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest), package `pkg_usertools-VERSION.zip`.  
**License:** [GPL-2.0-or-later](LICENSE).

## Contents

- [Installation and updates](#installation-and-updates)
- [Compare a user list](#compare-a-user-list)
- [Review inactive users](#review-inactive-users)
- [Delete inactive users](#delete-inactive-users)
- [Anonymise accounts](#anonymise-accounts)
- [Scheduled inactivity task and reports](#scheduled-inactivity-task)
- [Users dashboard summary](#users-dashboard-summary)
- [User groups module on the site](#user-groups-module-on-the-site)
- [Bulk add accounts to groups](#bulk-add-accounts-to-groups)
- [Remove from groups](#remove-from-groups)
- [Move groups](#move-groups)
- [Groups and access levels](#groups-and-access-levels)
- [Content by access level](#content-by-access-level)
- [Content by age](#content-by-age)
- [Access diagnosis](#access-diagnosis)
- [Security](#security)
- [Options](#options)
- [Tool screenshots](#tool-screenshots)
- [Development](#development)
- [Architecture map](docs/architecture.en.md)
- [Building and publishing](#building-and-publishing)

For specific questions, see the [FAQ](FAQ.en.md). If you are contributing or working with a coding agent, read [AGENTS.md](AGENTS.md) (Spanish).


The **User Tools** module on the Users dashboard provides direct links to individual tools according to the viewer's permissions, alongside its status indicators and the main User Tools link.

## Installation and updates

Download **pkg_usertools-VERSION.zip** from the [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest) and install it through System → Install → Extensions. The package installs the component, task plugin, “User Tools” administrator module and “User Tools - User Groups” site module. A fresh installation enables the “Task - User Tools” plugin; the first module installation publishes it on the Users dashboard. New tasks always start in dry-run mode.

Since 2026.10.3, Joomla notifies you of new versions under System → Update → Extensions, like any other extension: the package declares an update server pointing to the latest GitHub release. To update, install the new package over the existing installation; do not uninstall the component. Saved exclusions migrate automatically when updating from earlier versions.

The installer checks minimum versions, registers the extension with the User Actions Log and creates the “User Tools: account created” mail template. On uninstall, it warns that task reports remain in the logs directory.

## Compare a user list

**Example:** Joomla contains `ana`, `luis` and `marta`. If you paste a list containing `ana` and `marta`, the tool proposes blocking `luis`, unless protected or already blocked. If you add `pedro` and that account does not exist, you can select it for creation; it is not created automatically.

1. Open Components → User Tools → “Compare list”.
2. Paste the **complete** list of current usernames, one per line, and review exclusions.
3. The review shows accounts to block, including name, email, last visit and groups, with a filter for long lists. It also shows usernames not found in Joomla, which you may choose to create.
4. Confirm. The result links each account to Joomla's user manager. To undo a block, open the account and set its status to Enabled.

Since 2026.10.11, changes are applied **in batches** of a few seconds, so no request runs into PHP's or the web server's time limit (before, creating hundreds of accounts at once could end in a 500 error half-way). If they fit in one batch, the result appears directly. Otherwise a progress bar shows how many accounts are done and the page requests the next batch by itself. Each account is checked again, changed and written to the action log on its own, so an interruption loses nothing already done. If you close the tab or the connection drops, «Compare list» warns about the half-applied operation and offers to **continue** or **discard** the rest; meanwhile no other comparison can be applied. In temporary password mode, each password appears on the progress screen when its account is created (with a «Copy all» button) and is not stored anywhere: copy them before leaving.

Username comparison is case-insensitive: OTRO and otro are the same account. Empty lists are rejected. If **no username matches** an existing account, a red warning appears at the top of the review because all unprotected accounts would be proposed for blocking.

An incomplete list can cause incorrect blocks: always review the preview.

### Creating new accounts

New accounts are created in the **Registered** group, after checking that it exists and has no administrator access, with a different email address for each account. Choose between two modes for each operation:

- **Send a link to choose a password** (default). The account receives a random password that nobody sees, and the user receives an email linking to Joomla's standard password selection page. Edit the message under System → Mail Templates → “User Tools: account created”. Sending mail must be enabled in Global Configuration.
- **Show a temporary password.** A random password following Joomla's password policy (length and minimum digits, symbols, uppercase and lowercase letters) appears **once**, in the response that creates the account. Its clear text is not stored in the session, database or logs. The user must change it at first login.

When the list brings many new names, two aids in the review save work (since 2026.10.11):

- The box in the **Add** header ticks or unticks every new account at once.
- **Build the email from the username**: tick the box and type the domain (for example `example.com`, with or without `@`). Each empty email is filled as `username@example.com`. Emails typed by hand are not changed; changing the domain updates the generated ones, and unticking the box clears them. Names that cannot be used as an email (with spaces or accents, for example) stay empty and the screen says how many there are, so you can type their email. Check the emails before applying: in link mode, the access details are sent to them.

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

## Bulk add accounts to groups

Open **User Tools → Add accounts to groups** to add existing accounts to groups without removing other memberships. Enter usernames or emails and one target group, or `username;#ID` pairs on separate lines. Identify groups by `#ID` or a **full path** (for example `Public/Registered`); bare titles or duplicate paths are rejected. The preview distinguishes additions, existing memberships and exclusions. Only groups without administrator access can be assigned, and administrator accounts, Super Users and the operator are protected. Confirm and type the number of accounts. Changes use Joomla's User API, verify session closure and warn if sessions remain open or cannot be checked. Session closure can be retried from the result **without repeating memberships or their audit entries**. Every assignment is logged and large batches are resumable.

## Anonymise accounts

Since 2026.10.17, **Anonymise accounts** replaces the personal data of several accounts at once without deleting them. Joomla only does this one account at a time, from a removal request in Users → Privacy that the person has to confirm by email. It is **permanent**: it cannot be undone.

1. **Choose accounts**, in one of three ways:
   - **A list**: usernames or emails, one per line, not case-sensitive. Those that match no account are shown in the review.
   - **Every inactive account**: those, blocked or not, that have not signed in within the given period (730 days by default), optionally including those who never signed in.
   - **The accounts of a group**, with the “and its subgroups” checkbox to also include those in the groups below it.

   It has its own exclusions (administrators, usernames and groups), like the other tools.
2. **Select and confirm**: the accounts found, none ticked. Those still active are marked “Active”, because they will no longer be able to sign in. Below are those not offered and why: protected, already anonymised, or names with no account. You tick the confirmation and **always type the number of accounts**.
3. **Result**: the anonymised accounts, with their ID (which opens the account in Users), previous username (shown only on that screen) and new name and username, those skipped and any errors. Large batches run over several requests with the “Continue anonymising” button.

What happens to each account:

- **Joomla's privacy plugins** are asked and run, as for a completed removal request. The “Privacy - User Accounts” plugin changes the name to “User ID” and the number, the username to a random value and the email to `UserID…removed@email.invalid`, blocks the account and closes its sessions. Privacy plugins of other extensions clean their own data. If that plugin is disabled, the tool says so and does not search.
- User Tools also removes what that plugin leaves: the account's **profile fields**, **user notes** and **custom field values**.
- The account is **not deleted**: it keeps its ID and groups, so articles and other content still point to it, now without personal data.
- No request is created in Privacy, because nobody asked for one. Each anonymisation is written to the **User Actions Log** with the account ID and who did it only. Earlier log entries are not changed.
- Each account is checked again right before it is anonymised. If it signed in, changed groups or stopped meeting the criteria after the review, it is skipped.

It requires its own permission, *Anonymise accounts*. Super Users, your own account and, for non-Super Users, accounts with administrator access are never offered.

If profile, notes or custom-field cleanup fails, the account **has already been anonymised** and that action is logged, but the result reports **incomplete cleanup**. To retry, open Anonymise accounts, choose **Only retry the cleanup of already anonymised accounts**, select the anonymised username (or filter by group/inactivity), review and confirm. This mode offers **only** already anonymised and blocked accounts, never anonymises new accounts, and logs each completed cleanup separately.

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
- **Notices**: task plugin disabled or missing, no enabled task, a task stopped by the safety limit, a failed task or a report unfinished for more than an hour, tasks still in dry-run mode more than 14 days after creation and, since 2026.10.15, the [access diagnosis](#access-diagnosis) checks worth reviewing, only for users who can see it.

Since 2026.10.14, the counts are shown as “Inactive accounts to review” and “Blocked accounts to review”: they are candidate accounts, not automatic actions, and each card links to its review. Each task shows its mode, the result of its last run with a link to the report, and labelled dates; if it has no next run, the module says why. When any task is a dry run, a note explains that dry runs do not change accounts. The footer shows the update time and how the cache works.

Counts use the same rules as the tools, so they match what you see when opening them. They are calculated at most every **10 minutes**, and recalculated as soon as a tool or task changes accounts, or Options are saved.

The module respects permissions: only users with access to User Tools can see it; counts require inactivity review or deletion permission, and tasks require task-results permission. Configure it like any administrator module (Content → Administrator Modules, “User Tools”): move it to another dashboard, rename or unpublish it. Updates do not reposition a moved or unpublished module.

The shared screenshots show the Spanish interface.

![User Tools module on the Users dashboard](docs/screenshots/13-panel-usuarios.jpg)

## User groups module on the site

Since 2026.10.9, the package includes the **“User Tools - User Groups”** site module. Joomla has no module that shows on the site which groups someone belongs to or who is in a group; this one does it in two modes. The installer does not publish it: create it in Content → Site Modules → New.

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

## Remove from groups

Since 2026.10.9, **Remove from groups** takes an account out of the groups you choose and, if you ask, also out of every group below them: children, grandchildren and so on. Joomla's batch (“Remove from group” in Users) only removes the exact group you choose and does not show the account's groups first.

1. **Account**: type part of the username, name or email and pick the account from the list that appears while you type. The full username or email also works, not case-sensitive.
2. **Groups**: the groups the account belongs to are shown, all ticked and in their tree. Since 2026.10.16, intermediate groups the account is not in are shown without a checkbox and with the note “the account is not in this group”, so each group sits under its real parent. Untick those it should leave. When you untick a group that other groups of the account sit under, **“and its subgroups”** appears next to it: tick it and the groups below it are unticked, with the reason shown. Each group is decided on its own, so you can do it with several groups without one undoing another; untick “and its subgroups” or tick the group again and its subgroups get their state back. Parent groups are never touched.
3. **Review**: each group of the account as “Removed” or “Kept”, and which group causes a removal through “and its subgroups”. Nothing changes until you confirm. The review is single-use and expires after 15 minutes.
4. **Result**: the groups the account keeps, with links to its user record and the action log.

![Remove from groups: the account's groups in their tree, with an intermediate group without a checkbox (Spanish interface)](docs/screenshots/16-quitar-grupos-seleccion.jpg)

![Remove from groups: review (Spanish interface)](docs/screenshots/17-quitar-grupos-revision.jpg)

Rules:

- **If the account is left without groups**, it is assigned the group for new users from the Users Options (usually “Registered”). If that group is one of those being removed, or sits below one of them (it would inherit its permissions), nothing changes and the reason is shown.
- **It never changes your own account**: it cannot be chosen in the first step.
- **Non-Super Users never change accounts with administrator access** or Super Users: they cannot be chosen either.
- **A Super User can lose that status** only if at least one other active Super User remains; the review warns when the account loses that status or administrator access.
- The server applies “and its subgroups” too: even without JavaScript, a ticked group below one unticked with that option is removed, and the review shows it.
- Right before saving, the account's groups are checked against the reviewed ones; if they changed, nothing is applied.
- The change goes through Joomla's user API, **closes the account's open sessions** so the new permissions apply at once, and is written to the **User Actions Log** with the groups removed and, if any, the group added.
- **It checks that no session is left open.** If one survives, the group change is kept, but the result warns in red and offers to close them again, because the account keeps its old permissions while it stays open. With “Session Metadata” off this cannot be checked, and that is also reported.
- The list of accounts shown while typing is requested with the form token and the tool permission, shows at most 10 accounts, never your own, and changes nothing.

It requires its own permission, *Remove from groups*: Super Users have it; grant it to others in Options → Permissions. It works on one account at a time.

## Move groups

Since 2026.10.16, **Move groups** changes the parent of several groups at once. In Joomla, groups have to be edited one by one in Users → Groups. **Only Super Users can use it**, even if another account has “Configure ACL & Options” in User Tools.

1. **Groups and parent**: tick the groups in the tree and choose the new parent group. Each group moves with all its subgroups and keeps its members. If you tick a group and one of its subgroups, the subgroup goes with its group.
2. **Review**: for each group, the group it is under now, whether it moves or why not, how many accounts it affects (those in the group and its subgroups, blocked ones included) and which **access levels** its members gain and lose. Nothing has changed yet. The review is single-use, expires after 15 minutes and asks for confirmation.
3. **Result**: the groups moved and those skipped because they changed after the review.

A group is not moved, and the review says why, when:

- **it would change administrator access**: if, under the new parent, the group or any of its subgroups would gain or lose administrator access or Super User permissions. The review names every group affected. Those changes are made in Users → Groups, one group at a time;
- **it would change administrator access for an account**: Joomla decides an account's access with all its groups together, so, for example, a deny inherited through one group can cancel the access another gives. Each account in the ticked groups is checked with all its groups, and the review names those that would change;
- it is Public (the root) or a Super User group, or the target is a Super User group;
- the target is the group itself or one of its subgroups;
- there is already a group with the same name under the target (Joomla does not allow it);
- it is already under that group (nothing to do).

The change goes through Joomla's group model, the same as Users → Groups, so its checks and the plugin events run and the tree is rebuilt. Each group is checked again right before it moves, and every move is written to the **User Actions Log**. Then the **open sessions** of the accounts in the moved groups **are closed**, so the levels they lose apply at once; if a session survives, the result warns about it. Your own session is not closed.

![Move groups: review (Spanish interface)](docs/screenshots/22-mover-grupos-revision.jpg)

![Move groups: a move refused because of administrator access (Spanish interface)](docs/screenshots/23-mover-grupos-rechazo.jpg)

## Groups and access levels

Since 2026.10.9, **Groups and access levels** shows in a single table which viewing access levels each user group has. Joomla only shows this relation from the levels side (Users → Access Levels, groups column), without inheritance and without a per-group view. This screen is read only: it never changes groups, levels or accounts.

- **Rows**: the groups, in their tree, with the number of active accounts assigned directly (linked to the user manager filtered by that group) and labels for Super Users, groups with administrator access and the guest group set in the Users Options.
- **Columns**: the levels, in their order. Each cell says whether the group has the level:
  - **Assigned**: the group is assigned to the level.
  - **Inherited**: one of its parent groups is, and it is named. Joomla grants the level to every group below an assigned group; for example, “Editor” has the *Registered* level because it sits below “Registered”.
  - **Always**: the *Public* level, which Joomla grants to everyone even when nobody is assigned.
  - **—**: the group does not have the level.
- **Summary by level**: assigned groups and how many groups have the level in total. When some level has accounts assigned individually (without a group), the “Accounts assigned individually” column also appears; when there are none, it is hidden. A level with no groups or accounts is marked “No groups”, because nobody has it.

A marked cell always reads the same way: **the members of the row's group can see the content of the column's level**. “Inherited from group «Manager»” in the *Registered* column of the “Administrator” row means administrators see *Registered* content because their group sits under Manager, which is assigned to that level. It does not mean that whoever has the *Registered* level sees what administrators see.

![Groups and access levels (Spanish interface)](docs/screenshots/14-grupos-niveles.jpg)

A search box filters the groups. Next to it, two buttons download the information as CSV (since 2026.10.11):

- **CSV: table**: the same table as on screen, one row per group and one column per level. It includes each group's ID and full path (for example `Public / Manager / Administrator`) and the active accounts. Cells say “Assigned”, “Inherited from group «…»”, “Always” or are empty.
- **CSV: one row per level**: one row for each group and level it has, with the columns Group ID, Group, Path, Level ID, Access level, Type and Inherited from group. It is the handiest shape for filtering or a pivot table.

Both files are UTF-8 with BOM and semicolon-separated, so Excel opens them with accents intact, and they are protected against formulas. They always include every group, even when a filter is typed. Texts follow the administrator language. The download requires the same permission as the screen and the form token.

Group and level titles link to their edit screens in Joomla for users with the Admin permission on Users (the same one Joomla requires for those screens), and account counts link to the user manager for users who can manage users. The screen requires its own permission, *View groups, levels and content by level*: Super Users have it; grant it to others in Options → Permissions.

## Content by access level

Since 2026.10.12, **Content by access level** counts which items use each access level. Joomla only shows this list by list, with each one's «Access» filter, or when you try to delete the level, which says in which tables it is used but not which items or how many. This screen only reads.

- **Rows**: the levels, in their order, with each one's total number of items at the end. A search box filters the levels, handy when there are many.
- **Columns**: the kinds of item that have a level assigned:
  - articles;
  - categories of each component;
  - fields and field groups of each context;
  - site menu items;
  - site and administrator modules;
  - plugins, contacts, news feeds, tags, content languages and guided tours.
- **Links**: each number links to Joomla's list already filtered by that level, if you can manage that component. For example, «Categories: Articles» with the *Members* level opens Content → Categories with the access filter set.
- **Other extensions** (attachments, galleries…): their tables are found the same way Joomla does before deleting a level, by having an `access` column. They appear as «Table #__name» and are counted as a whole and without a link, because User Tools does not know what each row means. The Smart Search index is not counted, because it repeats the items above.
- **Warnings**: if items have a level that does not exist (for example, deleted directly in the database), they appear in an «ID n (missing)» row and a warning asks to give them an existing level, because nobody sees them on the site. The levels no item uses are dimmed, with a total of 0, and are also listed below the table.

Items in the trash are not counted. Joomla lists also hide archived items unless you change their status filter, so the list may show fewer items than the number. The **Export CSV** button downloads the same table, with one row per level and its ID (UTF-8 with BOM, semicolons). It uses the same permission as «Groups and access levels», now called *View groups, levels and content by level*, and is reached from the home screen or with that screen's «See content by level» button.

![Content by access level (Spanish interface)](docs/screenshots/18-contenido-por-nivel.jpg)

## Content by age

Since 2026.10.15, **Content by age** shows how much content has not been touched for a long time. Joomla's article list cannot be filtered by date, so this screen does that count. It is read only.

- **Rows**: the years in which some item was last modified, from the newest to the oldest, with each year's total at the end and a row of totals.
- **Columns**: one kind of item each. For now only **articles**; the table is ready for other kinds with a modification date.
- **Date**: the last modification date (UTC, as Joomla stores it). A 2015 article reviewed last month counts in the current year.
- **Statuses**: published, unpublished and archived items count; trashed ones do not.
- **Access level filter**: counts only the items with that level, to see which levels pile up old content.
- **List of one year**: each number opens the items of that year and kind, from the least recently modified to the most recent, with title, category, access level, author, status and creation and modification dates, 50 per page. The title links to Joomla's edit screen if you can edit it.

**Export CSV** downloads the table or, from a list, all its items (UTF-8 with BOM, semicolons). The screen requires the *View content by age* permission; the list of titles also requires being able to manage articles in Joomla. Filters, counts, totals, lists and CSV exports respect the authorized viewing levels of both articles and their categories, as Joomla’s article list does; global Super Users retain the full audit view. “Edit own” only links articles owned by the current user. Without it, the numbers are shown without a link.

![Content by age: table by year (Spanish interface)](docs/screenshots/20-contenido-antiguedad.jpg)

![Content by age: list of one year (Spanish interface)](docs/screenshots/21-contenido-antiguedad-lista.jpg)

## Access diagnosis

Since 2026.10.15, **Access diagnosis** brings together on one screen the situations worth reviewing in accounts, groups and access levels. It is read only: it does not fix permissions or change accounts. Every check is always shown, with or without notices, and when it finds something it explains what it found, **why it matters** and **how to review it**, with links to the matching account, group, level or Joomla list.

![Access diagnosis (Spanish interface)](docs/screenshots/19-diagnostico.jpg)

| Check | Type | What it points out |
|---|---|---|
| Accounts with administrator access and no recent activity | Review | Active accounts with administrator access that have not signed in for the last N days (90 by default, in Options → Diagnosis). Accounts that never signed in are counted from their registration. Super Users are included, even though the blocking and deletion tools protect them. |
| Active Super Users | Review | Warns when only one active Super User is left: if that account is lost, nobody can administer the site without access to the server. |
| Access levels with no groups or accounts | Review | Levels, except Public, with no group and no account assigned. Their content is only seen by Super Users. It says how many items use them. |
| Content with an access level that does not exist | Review | Items whose level does not exist, by kind, with a link to the Joomla list already filtered by that level. Nobody sees them on the site. The same tables as in «Content by access level» are used. |
| Groups with no members | Information | Groups with no accounts, either directly or in their subgroups, with their subgroups and the levels that use them. This is not usually an error: Joomla ships groups that many sites do not use. Public and the guest group are left out. |

The type is always written out («Review», «Information» or «No notices»), not shown by colour alone. **Export CSV** downloads every notice (UTF-8 with BOM, semicolons). The screen requires the *View the access diagnosis* permission, and the Users dashboard module tells users who have it how many checks are worth reviewing.

## Security

- **Super Users are always protected**, in every tool, even when “Exclude administrators” is disabled. User Tools does not block, delete or anonymise Super User accounts.
- **The account performing a manual operation is always protected.**
- **Each block, group change or anonymisation closes the account's open sessions**, as Joomla's user manager does (requires “Session Metadata”, enabled by default).
- **Creations, blocks, deletions, anonymisations and group changes are written to the User Actions Log** (Users → User Actions Log): who changed each account and which tool or scheduled task performed it.
- **Separate permissions**, delegable without granting Super User access (Options → Permissions):
  - *Compare list and block*, *Create accounts*, *Review inactive users*, *Delete inactive users*, *Anonymise accounts*, *View task results*, *Remove from groups*, *View groups, levels and content by level*, *View content by age* and *View the access diagnosis*.
  - Non-Super Users can never block, delete, anonymise or remove from groups accounts with administrator access.
- **Configurable reinforced confirmation**: from N accounts (10 by default), the exact number must be typed in addition to ticking the box.
- **Deletion and anonymisation always require typing the number of accounts**, even for one; the configurable threshold does not disable this confirmation.
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
| Anonymisation | Period and include never-logged-in users when choosing inactive accounts, and anonymisation exclusions |
| Diagnosis | Days without activity after which an account with administrator access appears in the access diagnosis (90 by default) |
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

Before publishing, update the version number in `com_usertools.xml`, `plugins/task/usertools/usertools.xml`, `modules/mod_usertools/mod_usertools.xml`, `modules/mod_usertools_groups/mod_usertools_groups.xml`, `package/pkg_usertools.xml` and `media/joomla.asset.json` in the same pull request. Check that all six values match, build without `--version`, verify the packages and merge with green CI. Then create a GitHub release with the **vYYYY.M.REVISION** tag on the commit in `main` that contains that version (Releases → Draft a new release → Choose a tag → Publish release). Creating the tag does not change the manifests: if it does not match them, publishing fails and rerunning the same job does not fix it. GitHub Actions runs tests, builds packages with that version in their manifests, and attaches them to the release with `SHA256SUMS.txt` and `updates.xml`. Existing installations receive updates through the update server. Prerelease suffixes are not supported.

## References

Independent implementation based on Joomla 6 APIs and the patterns of official task plugins such as [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Independent project with no official Joomla affiliation.
