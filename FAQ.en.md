# Frequently asked questions

[Español](FAQ.md) · **English**

Common questions about **Joomla User Tools**. For a complete description of each tool, see the [README](README.en.md).

**Contents:** [General](#general) · [Account profile](#account-profile) · [List comparison](#list-comparison) · [Accounts and passwords](#accounts-and-passwords) · [Inactivity and scheduled tasks](#inactivity-and-scheduled-tasks) · [Results and reports](#results-and-reports) · [Groups module on the site](#groups-module-on-the-site) · [Remove from groups](#remove-from-groups) · [Groups and access levels](#groups-and-access-levels) · [Installation and updates](#installation-and-updates)

### Can I export accounts with groups and MFA status?

Yes. **Export accounts to CSV** supports group, descendants, status and inactivity filters. Email is off by default and its export is logged. The MFA indicator never exposes secrets. The `usertools.export` permission is required. See [README](README.en.md#export-accounts-to-csv).

## General

### Does it delete users?

Only **Delete inactive users** does, and only the accounts you explicitly select, after confirming and typing the account count. All other tools only **block** accounts (Joomla's “Enabled” field); blocked accounts retain all data, groups and history. No tool reactivates accounts.

### Can I recover a deleted account?

No. Deletion is permanent. The User Actions Log retains its ID, username and the operator who deleted it, but not its data. If unsure, block it first with the inactivity review and delete it later using “Blocked only”.

### What happens to a deleted account's articles?

They remain, just as when deleting a user in Joomla, but are no longer associated with an existing account. Reassign them before deleting the account if needed.

### What is the difference between deleting and anonymising an account?

Both are permanent. **Deleting** removes the account: its content is left without an account. **Anonymising**, since 2026.10.17, keeps it blocked but replaces its name, username and email and removes its profile, notes and custom fields: content still points to the same account, now without personal data. You can anonymise the accounts of a list, every inactive account or those of a group. Details in the [README](README.en.md#anonymise-accounts).

### What if cleanup fails after an account has been anonymised?

The account **has already been anonymised and blocked**, and that anonymisation is recorded in the User Actions Log. The result shows **Incomplete cleanup** when profile, notes or custom-field data remains. To remove it, choose **Only retry the cleanup of already anonymised accounts**, select the accounts and confirm. This mode never anonymises new accounts and separately logs the completed cleanup.

### Why does no request appear in Users → Privacy when anonymising?

Because nobody asked for one: it is the administrator's decision, not the person's request. User Tools runs the same privacy plugins as a removal request, but does not invent the request. Each anonymisation is written to the User Actions Log with the account ID.

### I blocked an account by mistake. How do I restore it?

If User Tools blocked it, use **Undo blocks** (since 2026.10.19): choose the task run or manual review that blocked it, review and confirm. Only accounts that are still blocked and have not changed since are offered. Details in the [README](README.en.md#undo-blocks).

You can also do it in Joomla: open **Users → Manage**, select the account and use **Unblock**, or click the icon in the “Enabled” column. User Tools never unblocks accounts on its own.

### Which accounts are never blocked or deleted?

- Super Users, in every tool, even if “Exclude administrators” is disabled.
- The user performing a manual operation.
- When “Exclude administrators” is enabled (the default), any account allowed to enter the administrator interface.
- Non-Super Users can never block or delete accounts with administrator access.
- Accounts matching that tool's excluded usernames or groups (including child groups).

Meeting any one condition is sufficient.

### Are exclusions shared between tools?

No. List comparison, manual inactivity review, inactivity deletion and **each** scheduled task have their own exclusions. Changing one does not affect the others.

### Who can use it?

Super Users and users with the corresponding delegated permissions under **Options → Permissions**. There are separate permissions for comparison and blocking, account creation, inactivity review, inactivity deletion, viewing task results, removing from groups, viewing groups, levels and content by level, viewing content by age and viewing the access diagnosis. Deletion is restricted to Super Users until you explicitly grant it to others. Access to the component also requires `core.manage`.

### Does it need JavaScript?

Yes. Review screens use JavaScript to manage checkboxes and submit selections.

### How many users does it support?

Automated tests cover lists of 1,800 users. Review selections are submitted in a single JSON field to avoid PHP's `max_input_vars` limit on large lists.

### Where are the shortcuts to each tool?

On the **Users dashboard**, the User Tools module shows individual links to the tools you are allowed to use, alongside its indicators and the link to open the full component.

### Can I add many accounts to groups without removing current memberships?

Yes. **Add accounts to groups** accepts a list and a single group, or `username;#ID` pairs. It reviews changes and duplicates before confirmation and prevents administrator account changes or assignment of backend-access groups. Use unambiguous group IDs or full paths.

## Account profile

### Does viewing a profile change anything?

No. It brings together an account’s groups, access levels, status, dates, authored content and recent activity. Searching by username or email and opening its links never changes accounts. The dedicated `usertools.profile` permission is required.

### Does that permission let me see the User Actions Log?

No. Joomla restricts it to Super Users and the profile keeps that restriction. When you can view it, the profile distinguishes the account as the action’s actor or affected user, shows up to 50 matches among the 1000 recent actor/item candidates and links each entry. It does not show original message payloads that could contain private data.

### Why do some counts have no author-filter link?

Joomla can filter articles by author but has no such filter in its category and contact lists. The profile shows their counts and explains the limitation. It counts all states, including the trash, within the types and viewing levels you are allowed to access.

### Does the block link apply the inactivity filter?

No: you explicitly selected one account from its profile. The link only opens that account’s review; clicking it does not block anything. Confirmation and the typed count from the configured threshold are still required. The account is checked again before saving; if it changed, it is skipped without substitution. Exclusions remain in force.

### Why are there no action links for an account?

Each link needs its tool’s own permission. The operator’s own account and Super Users never have these links; a non-Super User also cannot act on administrator accounts. Blocking is not offered when the account is already blocked, and blocking and anonymisation exclusions are respected. The profile only shows whether MFA is configured, never its secrets, methods or codes.

## List comparison

### What format should the list use?

One username per line. A column copied from Excel works. Multi-column CSV is not accepted. Leading/trailing whitespace, empty lines and duplicates are removed.

### Is comparison case-sensitive?

No. `OTRO` and `otro` are the same account, both in comparisons and username exclusions.

### What if I paste an incomplete list?

Every missing account is proposed for blocking unless protected. Always paste the **complete** list and review changes before confirming. If **no** username matches an existing account, a red warning suggests that the wrong list may have been pasted.

### Does “Next: review changes” apply anything?

No. That step only saves exclusions and calculates the preview. Account changes are applied only after ticking the confirmation box and clicking “Confirm and apply changes”.

### The preview has expired

Previews last **15 minutes** and can be applied only once. They also become invalid if exclusions change or the proposed set of accounts to block changes. Compare again; your entered list is retained. Return to the first step (“Back and edit” preserves your data) and generate a new review.

### Why is a user from the list absent from every section?

Existing accounts count as matches and remain unchanged. Already blocked accounts are not proposed again.

## Accounts and passwords

### Are new usernames from the list created automatically?

No. They appear **unselected** in the review. Only selected accounts are created, each with a different email address that is not already used in Joomla.

### There are many new names: do I have to tick them and type the emails one by one?

No. Since 2026.10.11, the box in the **Add** header ticks them all, and the **Build the email from the username** option fills each empty email as `username@domain` with the domain you type. Emails you typed by hand are not touched, and names that cannot be used as an email (with spaces or accents) stay empty for you to complete. Details in the [README](README.en.md#creating-new-accounts).

### Which group receives new accounts?

**Registered** (group ID 2). The tool checks that the group exists and does not grant administrator access. The initial display name is the username.

### Is a welcome email sent?

There are two creation modes. By default, an email with a password-selection link is sent using the editable “User Tools: account created” template. Alternatively, show a temporary password once and communicate it yourself. Both modes suppress Joomla's user-plugin welcome email and then restore that plugin's settings. Other plugins still receive the normal account-creation events.

### I lost the temporary password

Passwords appear **once** on the result screen and are not saved or logged in clear text. After reloading or leaving, they cannot be recovered: use Joomla's password recovery or assign a new password under **Users → Manage**. The account will still require a password change at first login.

### I created hundreds of accounts and got a 500 error half-way

Up to 2026.10.10 everything ran in one request, and with hundreds of creations (each one saves the account and sends an email) the server's time limit was exceeded. The accounts created before the cut exist, but they were not written to the action log. Since 2026.10.11 changes are applied in batches with a progress bar, and each creation is logged as soon as it happens. To finish an interrupted list, compare it again: the accounts already created match and only the missing ones appear as new. More details in the [README](README.en.md#compare-a-user-list).

### The tab was closed while the changes were being applied

What was done until then is kept and logged. When you open «Compare list» you will see a notice with how many accounts are done and the **Continue** and **Stop and discard the rest** buttons. When continuing, each account is checked again before changing it, the job resumes exactly where it stopped (progress is saved after each account, so none is repeated) and it checks again that you are still allowed to create accounts. In temporary password mode, passwords already shown cannot be recovered: use Joomla's password reset for those accounts.

### One account creation failed. Is everything rolled back?

If the selection is invalid before processing starts (duplicate email, existing username, etc.), nothing is applied. Failures during processing appear in the result, but changes already made are not rolled back.

## Inactivity and scheduled tasks

### How does manual review differ from a scheduled task?

**Manual review** finds candidates and lets you select which accounts to suspend; none is initially selected. The **scheduled task** automatically blocks all candidates each time it runs, unless dry-run mode is enabled.

### How can I test a task without blocking accounts?

New tasks have **Dry run (no blocking)** enabled. Run the task and inspect the proposed accounts under **Task results**. Turn dry-run mode off once satisfied.

### How is the period calculated?

The last visit is compared with a **UTC** cutoff: now minus the configured days (365 by default, from 1 to 36,500). The last visit must be strictly earlier than the cutoff. 365 days is a fixed duration, not a calendar year.

### Why is a user who never logged in not blocked?

Accounts with no visits are excluded by default. Enable “Include users who have never logged in” to include them; the period then starts from their **registration date**, so newly created accounts are not proposed.

### What about someone with an open session?

Inactivity tools skip accounts with a recent row in Joomla's session table, within the session lifetime configured in Global Configuration. When blocking an account, User Tools asks Joomla to close its sessions. Both operations depend on Joomla session metadata.

### The task does not run automatically

Check that **Task - User Tools** is enabled and the task has a schedule other than “Manual execution”. Joomla's default scheduler depends on visits; to run without traffic, configure your hosting cron using the [official Joomla guide](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### Can I have tasks with different periods?

Yes. Each scheduled task has its own period, dry-run mode and exclusions; for example, a 365-day task for everyone and a stricter task for one group.

### Why does a task stop for safety?

The mass-blocking limit stops a real run when candidates exceed the configured percentage of active accounts and meet the minimum candidate count. Defaults: more than 20% and at least 10 candidates. Dry-run mode only warns. Review the report and settings before changing limits.

## Results and reports

### Where can I see what a task did?

Under **Components → User Tools → Task results**. Each run shows its mode (dry run or real), status and counts. “View report” opens blocked, simulated, protected and failed accounts, plus omissions summarized by reason. You can filter runs and export each report to CSV.

### Where are reports stored, and do they take up space?

In the `usertools-reports` subfolder of Joomla's logs directory, protected against direct web access. They contain no passwords or email addresses. Reports are removed after task runs according to **Options** retention: 90 days by default; 0 keeps them forever.

### Why are old runs missing?

Results are recorded only from the version that introduced reporting; earlier runs are not imported. For those, check **System → Scheduled Tasks → Execution History**, or individual task logs if enabled.

### Is there a summary outside the component?

Yes, since 2026.10.6. The administrator **Users** dashboard shows a “User Tools” module with unblocked inactive accounts, blocked accounts pending deletion, scheduled-task status and notices (disabled plugin, task stopped for safety or failed, dry-run mode lasting over 14 days, etc.). Each count or notice links to the corresponding tool or report.

### What does “Blocked accounts to review” mean?

These are blocked accounts meeting the deletion tool’s period and exclusions. The count opens a review: it does not delete accounts or mean that they should be deleted without checking them.

### Why does a task have no next run?

The module distinguishes a disabled task from an enabled task without a next run date. Check its configuration for the execution rule. A previous result does not mean that the task is still enabled.

### How often does the dashboard summary update?

It is cached for up to 10 minutes. It is recalculated earlier when a tool or task changes accounts, or Options are saved. The data timestamp appears at the bottom of the module.

### How do I move or remove the dashboard module?

Under **Content → Administrator Modules**, find “User Tools”. Change its position (for example, to the main dashboard), rename it or unpublish it. Package updates do not reposition it.

### Why can a user see the module without counts?

The module only shows information permitted by the user's permissions: counts require *Review inactive users* or *Delete inactive users*, and task information requires *View task results*. Users without access to User Tools do not see the module.

### A report appears “unfinished”

The run was interrupted. Reports are saved at the start, every 50 users and at the end, so they reflect progress saved before the interruption; already applied blocks remain.

## Groups module on the site

### How do I show on the site the groups of a user or the members of a group?

With the “User Tools - User Groups” module, included since 2026.10.9. The installer does not publish it: create it in Content → Site Modules → New and choose *Groups of a user* or *Members of a group* in “What to show”. Details are in the [README](README.en.md#user-groups-module-on-the-site).

### Why don't guests see the module?

In *Groups of a user* mode with *Logged-in visitor*, the module shows each person their own groups; someone who has not logged in has no groups to see, so it does not appear. To always show the groups of the same account, choose *A specific user*.

### Why is someone missing from the member list?

It may be a blocked account, which is never listed, or an account with administrator access, which is hidden while *Hide accounts with administrator access* is on. If they belong to a subgroup, turn on *Include subgroups*. And if the group exceeds *Maximum members*, the module says how many are left.

### Does it expose personal data?

It shows names or usernames, never email addresses. Decide who sees it with the module's **Access** field (for example, *Registered*) and keep accounts with administrator access hidden unless you have a reason to show them.

## Remove from groups

### How is it different from Joomla's “Remove from group” batch?

Joomla's batch removes only the exact group you choose, without showing the account's groups first. **Remove from groups** shows only the groups it has, lets you untick several and, with “and its subgroups”, also removes every group below them (children, grandchildren…). Before applying it shows what the account loses and, when applied, it closes its sessions. Details are in the [README](README.en.md#remove-from-groups).

### What exactly does “and its subgroups” do?

It appears when you untick a group that other groups of the account sit under. If the account is in none of the groups below it, there is nothing to remove in cascade and the option is not shown; since 2026.10.16 the intermediate groups the account is not in are drawn without a checkbox, so you can see which group each one really sits under. If you tick it, the account also leaves every group below that one that it belongs to, however many levels down. It is decided group by group: you can use it on several groups and each keeps its own choice. Parent groups are not touched. Without it, the account only leaves the group you untick.

### What happens if the account is left without groups?

It is assigned the group for new users from the Users Options. If that group is one of those being removed, or sits below one of them (it would inherit its permissions), nothing changes: keep a group ticked or assign it another one in the user manager first.

### Why can't I choose an account or apply the change?

The tool gives the reason: it is your own account; it has administrator access and you are not a Super User; it is the last active Super User; or you did not untick any group.

### Can I remove several accounts at once?

No: the tool works with one account at a time, so every permission change is reviewed separately.

### Can I change the parent of several groups at once?

Yes, since 2026.10.16, with **Move groups**, if you are a Super User. Tick the groups, choose the new parent, and the review shows which access levels their members gain or lose before you confirm. It does not move Super User groups or any group whose move would change administrator access for it or its subgroups: those changes are made in Users → Groups, one at a time. Details in the [README](README.en.md#move-groups).

## Groups and access levels

### How do I see which access levels each group has?

With **Groups and access levels**, since 2026.10.9. It shows a table with groups as rows and levels as columns. Details are in the [README](README.en.md#groups-and-access-levels).

### Why does a group show “Inherited” if I did not assign it to the level?

Because Joomla grants the level to every group below an assigned one. The cell names the parent group it comes from. To remove it, take that parent group out of the level or move the group in the tree; the User Tools screen changes nothing.

### “Inherited” in the *Public* column of the “Administrator” row: does it mean whoever has the Public level sees what administrators see?

No, the other way round. Each cell says which levels the members of the row's group have, that is, which content they can see. That cell means administrators can see *Public* content, because their group sits under the “Public” group, assigned to that level. The text below it (“from group «Public»”) names the **group** the inheritance comes from, not the level.

### Can I export the table?

Yes, since 2026.10.11, as CSV in two shapes: the table as shown (one row per group and one column per level) or one row for each group and level it has, handier for filtering or pivot tables. They open correctly in Excel (UTF-8 with BOM, semicolons). Details in the [README](README.en.md#groups-and-access-levels).

### I do not see the “Accounts assigned individually” column

It only appears when some level has accounts assigned directly, without a group. Joomla's access level screen does not offer that, so normally there are none.

### How do I see which content each access level has?

With **Content by access level**, since 2026.10.12. For each level it counts the articles, categories, menu items, modules, fields, plugins and other items that use it, and each number links to Joomla's list already filtered. Tables of other extensions with an `access` column, such as attachments, are counted as a whole. Details in the [README](README.en.md#content-by-access-level).

### Can I delete an access level without breaking anything?

Joomla does not let you delete a level used by any item, including those in the trash. «Content by access level» shows which items use it, so you can change their level first, and the levels no item uses are listed below the table. Keep in mind that a level can also be used only to grant view permissions to groups without having content of its own.

### Why can't I see the screen?

It requires the *View groups, levels and content by level* permission. Super Users have it; for others, grant it in Components → User Tools → Options → Permissions.

## Content by age

### How do I see the articles nobody has touched for years?

With **Content by age**, since 2026.10.15. It shows a table by year based on the last modification date, and each number opens the list of those articles with a link to edit them. It also counts article categories, contacts and news feeds. Details in the [README](README.en.md#content-by-age).

### Why are modules, menus and plugins not shown?

Because Joomla does not store when they were last modified, and without that date they cannot be split by year. Only kinds with both a creation and a modification date are shown.

### Why the modification date and not the creation date?

Because the point is to find abandoned content: an old article reviewed recently is not abandoned. If an item has no modification date, its creation date is used.

### Are archived and trashed items counted?

Archived ones are, with their status shown in the list, because they are often exactly the ones worth reviewing. Trashed ones are not.

### Why can I see the numbers but not open the list?

The list shows titles, so besides the *View content by age* permission you need to be able to manage that kind in Joomla (articles and categories in Content, contacts in Contacts, feeds in News Feeds). Counts, lists and CSV exports also respect the authorized viewing levels of the item and its category (categories, only their own, as in Joomla). Edit-own permission only offers links for items created by the current user.

## Access diagnosis

### What does the access diagnosis check?

Since 2026.10.15, five things: accounts with administrator access that have not signed in for a while, whether only one active Super User is left, access levels with no group and no account, content with a level that does not exist, and groups with no members. Each notice explains what it found, why it matters and how to review it. Details in the [README](README.en.md#access-diagnosis).

### Does it fix anything?

No. It is read only. Fixes are made in Joomla (Users, Access Levels, Groups or each content list) or with the User Tools tools linked from each notice.

### Why are Super Users listed if User Tools never blocks them?

Because an unused Super User account is exactly the one most worth reviewing. The blocking and deletion tools still protect them; the diagnosis only points them out so you can decide.

### How do I change the diagnosis inactivity period?

In Components → User Tools → Options → **Diagnosis**. It is 90 days by default and does not depend on the «Review inactive users» period.

### Groups such as Publisher are listed. Is that an error?

No. «Groups with no members» is for information: Joomla ships groups that many sites do not use. It helps find groups that are no longer needed. Before deleting one, check that no permission and no access level uses it.

### Why can't I see the diagnosis?

It requires the *View the access diagnosis* permission. Super Users have it; grant it to others in Components → User Tools → Options → Permissions.

## Installation and updates

### How do I open a User Tools category directly?

Expand **Components → User Tools**: Home keeps the complete home screen and the other four entries open individual categories. Only categories containing tools you may use appear. Dashboard shortcuts follow the same classification; a visible category grants no extra permissions. See [category navigation](README.en.md#category-navigation).

### Why is a category missing or the current tool not marked?

A category disappears when you have no permission for any of its tools. Task configuration also requires permission to manage Joomla's scheduler. If filtering or the active category is not working, check that **System - User Tools** is enabled under Plugins. The package enables it once when first added; later updates preserve an explicit disable. Direct URLs still require their screen permissions.

### Which Joomla version is required?

Joomla 6. Documentation screenshots were taken on Joomla 6.2.0.

### Which file should I install?

**pkg_usertools-VERSION.zip** from the [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest). It installs or updates the component, task and navigation plugins, dashboard module and site groups module together. Separate `com_usertools`, `plg_task_usertools`, `plg_system_usertools`, `mod_usertools` and `mod_usertools_groups` ZIPs are for separate installations and must use the same version. The component needs the system plugin to filter its menu and mark the active category.

### How do I update without losing settings?

Install the new package over the existing installation through **System → Install → Extensions**. Do not uninstall the component: doing so loses settings and exclusions.

### How are versions numbered?

**year.month.revision**, without a leading zero in the month (2026.10.0, 2026.10.1, 2026.11.0, etc.). GitHub tags have a `v` prefix.

### Where do I report a bug?

Open a [GitHub issue](https://github.com/Danieldiazi/joomla-user-tools/issues) with the Joomla and extension versions, reproduction steps and, if possible, a screenshot.
