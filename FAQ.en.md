# Frequently asked questions

[Español](FAQ.md) · **English**

Common questions about **Joomla User Tools**. For a complete description of each tool, see the [README](README.en.md).

**Contents:** [General](#general) · [List comparison](#list-comparison) · [Accounts and passwords](#accounts-and-passwords) · [Inactivity and scheduled tasks](#inactivity-and-scheduled-tasks) · [Results and reports](#results-and-reports) · [Groups module on the site](#groups-module-on-the-site) · [Remove from a group](#remove-from-a-group) · [Groups and access levels](#groups-and-access-levels) · [Installation and updates](#installation-and-updates)

## General

### Does it delete users?

Only **Delete inactive users** does, and only the accounts you explicitly select, after confirming and typing the account count. All other tools only **block** accounts (Joomla's “Enabled” field); blocked accounts retain all data, groups and history. No tool reactivates accounts.

### Can I recover a deleted account?

No. Deletion is permanent. The User Actions Log retains its ID, username and the operator who deleted it, but not its data. If unsure, block it first with the inactivity review and delete it later using “Blocked only”.

### What happens to a deleted account's articles?

They remain, just as when deleting a user in Joomla, but are no longer associated with an existing account. Reassign them before deleting the account if needed.

### I blocked an account by mistake. How do I restore it?

In Joomla, open **Users → Manage**, select the account and use **Unblock**, or click the icon in the “Enabled” column. User Tools does not automatically undo blocks.

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

Super Users and users with the corresponding delegated permissions under **Options → Permissions**. There are separate permissions for comparison and blocking, account creation, inactivity review, inactivity deletion and viewing task results. Deletion is restricted to Super Users until you explicitly grant it to others. Access to the component also requires `core.manage`.

### Does it need JavaScript?

Yes. Review screens use JavaScript to manage checkboxes and submit selections.

### How many users does it support?

Automated tests cover lists of 1,800 users. Review selections are submitted in a single JSON field to avoid PHP's `max_input_vars` limit on large lists.

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

### Which group receives new accounts?

**Registered** (group ID 2). The tool checks that the group exists and does not grant administrator access. The initial display name is the username.

### Is a welcome email sent?

There are two creation modes. By default, an email with a password-selection link is sent using the editable “User Tools: account created” template. Alternatively, show a temporary password once and communicate it yourself. Both modes suppress Joomla's user-plugin welcome email and then restore that plugin's settings. Other plugins still receive the normal account-creation events.

### I lost the temporary password

Passwords appear **once** on the result screen and are not saved or logged in clear text. After reloading or leaving, they cannot be recovered: use Joomla's password recovery or assign a new password under **Users → Manage**. The account will still require a password change at first login.

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

With the “User Tools - User Groups” module, included since 2026.10.7. The installer does not publish it: create it in Content → Site Modules → New and choose *Groups of a user* or *Members of a group* in “What to show”. Details are in the [README](README.en.md#user-groups-module-on-the-site).

### Why don't guests see the module?

In *Groups of a user* mode with *Logged-in visitor*, the module shows each person their own groups; someone who has not logged in has no groups to see, so it does not appear. To always show the groups of the same account, choose *A specific user*.

### Why is someone missing from the member list?

It may be a blocked account, which is never listed, or an account with administrator access, which is hidden while *Hide accounts with administrator access* is on. If they belong to a subgroup, turn on *Include subgroups*. And if the group exceeds *Maximum members*, the module says how many are left.

### Does it expose personal data?

It shows names or usernames, never email addresses. Decide who sees it with the module's **Access** field (for example, *Registered*) and keep accounts with administrator access hidden unless you have a reason to show them.

## Remove from a group

### How is it different from Joomla's “Remove from group” batch?

Joomla's batch removes only the exact group you choose. **Remove from a group** also removes every group below it (children, grandchildren…), shows beforehand which groups the account loses and closes its sessions when applied. Details are in the [README](README.en.md#remove-from-a-group).

### What happens if the account is left without groups?

It is assigned the group for new users from the Users Options. If that group is one of those being removed, nothing changes: assign it another group in the user manager first.

### Why can't I apply the change to an account?

The review gives the reason: it is your own account; it has administrator access and you are not a Super User; it is the last active Super User; or it is not in that group or any of its subgroups.

### Can I remove several accounts at once?

No: the tool works with one account at a time, so every permission change is reviewed separately.

## Groups and access levels

### How do I see which access levels each group has?

With **Groups and access levels**, since 2026.10.8. It shows a table with groups as rows and levels as columns. Details are in the [README](README.en.md#groups-and-access-levels).

### Why does a group show “Inherited” if I did not assign it to the level?

Because Joomla grants the level to every group below an assigned one. The cell names the parent group it comes from. To remove it, take that parent group out of the level or move the group in the tree; the User Tools screen changes nothing.

### Why can't I see the screen?

It requires the *View groups and access levels* permission. Super Users have it; for others, grant it in Components → User Tools → Options → Permissions.

## Installation and updates

### Which Joomla version is required?

Joomla 6. Documentation screenshots were taken on Joomla 6.2.0.

### Which file should I install?

**pkg_usertools-VERSION.zip** from the [latest release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest). It installs or updates the component, plugin, dashboard module and site groups module together. Separate `com_usertools`, `plg_task_usertools`, `mod_usertools` and `mod_usertools_groups` ZIPs are for separate installations and must use the same version.

### How do I update without losing settings?

Install the new package over the existing installation through **System → Install → Extensions**. Do not uninstall the component: doing so loses settings and exclusions.

### How are versions numbered?

**year.month.revision**, without a leading zero in the month (2026.10.0, 2026.10.1, 2026.11.0, etc.). GitHub tags have a `v` prefix.

### Where do I report a bug?

Open a [GitHub issue](https://github.com/Danieldiazi/joomla-user-tools/issues) with the Joomla and extension versions, reproduction steps and, if possible, a screenshot.
