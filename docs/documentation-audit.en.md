# Internal source documentation audit — issue #72

[Español](documentation-audit.md) · **English**

[Onboarding guide](development.en.md) · [Architecture](architecture.en.md) · [Issue #72](https://github.com/Danieldiazi/joomla-user-tools/issues/72)

## Purpose and method

Review of **internal** documentation in the project's PHP/JavaScript source files: class/method headers and PHPDoc, inline comments, responsibilities, explanations of invariants, and consistency with architecture. Sources on `main` were inspected, with focused review of the most sensitive flows. This is **not** a functional, security, performance or API-correctness audit; missing PHPDoc on trivial adapters is not automatically a defect.

**Inventory: 183 source files** (component, views/templates, modules, plugins, installer, scripts and JS; excluding tests and manifests). Tests can be located through the [architecture](architecture.en.md) matrix. Status reflects the documentation assessment and file responsibility, not comment counts.

## Classification

- **A — Amended**: a source comment or documentation contract was improved in this PR, without functional changes.
- **S — Consider on future changes**: important logic or contract already has useful explanations; generic extra comments are not justified at present.
- **R — Reviewed**: no concrete gap identified that calls for a source change in this PR.
- **D — Delegation/infrastructure**: thin controller or service provider; behaviour is evident from the APIs and delegated classes.
- **V — View/presentation**: does not need line-by-line comments; rules and authorization should be documented in their owning layers.

## Findings and resolutions

| Priority | File or method | Observation and action |
|---|---|---|
| High | `docs/arquitectura.md` and `docs/architecture.en.md` | Heading incorrectly presented release 2026.10.5 as current; corrected to describe `main`. |
| High | Architecture, persistence and partial failures | Session results and batched jobs were not distinguished sufficiently from persistent undo-block reports; corrected in both languages. |
| Medium | `plugins/task/usertools/src/Extension/Usertools.php::blockInactive()` | No local description of scheduler execution/failure contract; added PHPDoc for system identity, up-front report, revalidation, checkpoints and result. |
| Medium | `admin/src/Model/AnonymizeModel.php::step()` | Single-line description did not explain revalidation or per-account checkpoints; expanded. |
| Medium | `admin/src/Service/ReportStore.php` | Class comment implied only task reports although callers can also store manual block batches; corrected. |
| Medium | `admin/src/Service/AccountGroupAdder.php::add()` | Contract did not explain `safeTarget`, the stale state or the distinction between saving and failing to close sessions; clarified. |
| Medium | `plugins/system/usertools/src/Extension/Usertools.php::onPreprocessMenuItems()` | Clarified that hiding menu links is a convenience, never a replacement for controller authorization. |
| Medium | `package/script.php` | Installer responsibility summary had not kept up with plugins/modules; updated. |
| Medium | `AGENTS.md`, README and new onboarding guide | Added a «document rationale» policy, a request walkthrough, risks and tests, in Spanish and English. |

### Assessed areas not changed unnecessarily

- `AccountAnonymizer` already distinguishes verified changes, privacy plugin effects and scope limitations. This is a useful pattern for rationale comments.
- `UserDirectory`, `GroupMove` and `GroupRemoval` contain meaningful contracts and business rules. Long methods such as `CompareModel::step()` deserve attention in a future refactor, not superficial comments.
- Providers, thin controllers, simple getters, templates and views do not need indiscriminate PHPDoc. JavaScript already explains cascade behaviour and operator choices; assignment-by-assignment comments would add noise.
- **No broad set of redundant comments was identified that would justify mass deletion.** The most significant confirmed stale explanation was in architecture documentation, rather than a PHP instruction.
- Missing comments are not treated as functional defects. When a flow changes, maintaining its documentation in the same PR is part of `AGENTS.md`'s acceptance criteria.

## File-by-file inventory

| File | Status |
|---|---|
| **admin/layouts/** | |
| `admin/layouts/usertools/account.php` | V |
| `admin/layouts/usertools/confirm.php` | V |
| `admin/layouts/usertools/exclusions.php` | V |
| `admin/layouts/usertools/steps.php` | V |
| `admin/layouts/usertools/undo.php` | V |
| **admin/** | |
| `admin/services/provider.php` | D |
| **admin/src/Controller/** | |
| `admin/src/Controller/AccessController.php` | D |
| `admin/src/Controller/AccountsController.php` | D |
| `admin/src/Controller/AddgroupsController.php` | D |
| `admin/src/Controller/AgeController.php` | D |
| `admin/src/Controller/AnonymizeController.php` | D |
| `admin/src/Controller/CompareController.php` | D |
| `admin/src/Controller/DeleteController.php` | D |
| `admin/src/Controller/DiagnoseController.php` | D |
| `admin/src/Controller/DisplayController.php` | D |
| `admin/src/Controller/ExportController.php` | D |
| `admin/src/Controller/HandoverController.php` | D |
| `admin/src/Controller/InactiveController.php` | D |
| `admin/src/Controller/MoveController.php` | D |
| `admin/src/Controller/ProfileController.php` | D |
| `admin/src/Controller/ReportsController.php` | D |
| `admin/src/Controller/SyncController.php` | D |
| `admin/src/Controller/ToolControllerTrait.php` | D |
| `admin/src/Controller/UnblockController.php` | D |
| `admin/src/Controller/UngroupController.php` | D |
| `admin/src/Controller/UsageController.php` | D |
| **admin/src/Model/** | |
| `admin/src/Model/AccessModel.php` | R |
| `admin/src/Model/AddgroupsModel.php` | S |
| `admin/src/Model/AgeModel.php` | R |
| `admin/src/Model/AnonymizeModel.php` | A |
| `admin/src/Model/CompareModel.php` | S |
| `admin/src/Model/DeleteModel.php` | S |
| `admin/src/Model/DiagnoseModel.php` | R |
| `admin/src/Model/ExplainModel.php` | R |
| `admin/src/Model/ExportModel.php` | R |
| `admin/src/Model/HandoverModel.php` | R |
| `admin/src/Model/InactiveModel.php` | R |
| `admin/src/Model/MoveModel.php` | R |
| `admin/src/Model/ProfileModel.php` | R |
| `admin/src/Model/ReportModel.php` | R |
| `admin/src/Model/ReportsModel.php` | R |
| `admin/src/Model/SyncModel.php` | R |
| `admin/src/Model/ToolStateTrait.php` | S |
| `admin/src/Model/UnblockModel.php` | R |
| `admin/src/Model/UngroupModel.php` | S |
| `admin/src/Model/UsageModel.php` | R |
| **admin/src/Service/** | |
| `admin/src/Service/AccessExplanation.php` | S |
| `admin/src/Service/AccessExplanationReader.php` | S |
| `admin/src/Service/AccessHealth.php` | R |
| `admin/src/Service/AccessMatrix.php` | R |
| `admin/src/Service/AccessUsage.php` | R |
| `admin/src/Service/AccessUsageReader.php` | R |
| `admin/src/Service/AccountAnonymizer.php` | S |
| `admin/src/Service/AccountBlocker.php` | R |
| `admin/src/Service/AccountDeleter.php` | R |
| `admin/src/Service/AccountExport.php` | R |
| `admin/src/Service/AccountExportReader.php` | R |
| `admin/src/Service/AccountGroupAdder.php` | A |
| `admin/src/Service/AccountGroupSyncer.php` | R |
| `admin/src/Service/AccountIdentity.php` | R |
| `admin/src/Service/AccountMailer.php` | R |
| `admin/src/Service/AccountProfile.php` | R |
| `admin/src/Service/AccountProfileReader.php` | R |
| `admin/src/Service/AccountRegrouper.php` | R |
| `admin/src/Service/AccountUnblocker.php` | R |
| `admin/src/Service/ActionLogger.php` | S |
| `admin/src/Service/AnonymizeSelection.php` | R |
| `admin/src/Service/ArticleReassigner.php` | R |
| `admin/src/Service/BlockBatch.php` | R |
| `admin/src/Service/BulkGroupAssignments.php` | R |
| `admin/src/Service/Comparison.php` | R |
| `admin/src/Service/ContentAge.php` | R |
| `admin/src/Service/ContentAgeReader.php` | R |
| `admin/src/Service/ContentHandover.php` | R |
| `admin/src/Service/ContentHandoverReader.php` | R |
| `admin/src/Service/CsvExport.php` | S |
| `admin/src/Service/DashboardSummary.php` | R |
| `admin/src/Service/Exclusions.php` | R |
| `admin/src/Service/GroupMove.php` | S |
| `admin/src/Service/GroupMover.php` | R |
| `admin/src/Service/GroupRemoval.php` | S |
| `admin/src/Service/GroupSync.php` | R |
| `admin/src/Service/GuidedTours.php` | R |
| `admin/src/Service/InactiveSelection.php` | R |
| `admin/src/Service/InactivityPolicy.php` | R |
| `admin/src/Service/MassBlockGuard.php` | R |
| `admin/src/Service/Navigation.php` | R |
| `admin/src/Service/Operator.php` | R |
| `admin/src/Service/PasswordGenerator.php` | R |
| `admin/src/Service/Registration.php` | R |
| `admin/src/Service/ReportStore.php` | A |
| `admin/src/Service/Settings.php` | R |
| `admin/src/Service/SettingsStore.php` | R |
| `admin/src/Service/Toolkit.php` | R |
| `admin/src/Service/UserDirectory.php` | S |
| `admin/src/Service/UserFacingException.php` | R |
| `admin/src/Service/UserRecord.php` | R |
| **admin/src/View/** | |
| `admin/src/View/Access/HtmlView.php` | V |
| `admin/src/View/Addgroups/HtmlView.php` | V |
| `admin/src/View/Age/HtmlView.php` | V |
| `admin/src/View/Anonymize/HtmlView.php` | V |
| `admin/src/View/Compare/HtmlView.php` | V |
| `admin/src/View/Delete/HtmlView.php` | V |
| `admin/src/View/Diagnose/HtmlView.php` | V |
| `admin/src/View/Explain/HtmlView.php` | V |
| `admin/src/View/Export/HtmlView.php` | V |
| `admin/src/View/Handover/HtmlView.php` | V |
| `admin/src/View/Home/HtmlView.php` | V |
| `admin/src/View/Inactive/HtmlView.php` | V |
| `admin/src/View/Move/HtmlView.php` | V |
| `admin/src/View/Profile/HtmlView.php` | V |
| `admin/src/View/Report/HtmlView.php` | V |
| `admin/src/View/Reports/HtmlView.php` | V |
| `admin/src/View/Schedule/HtmlView.php` | V |
| `admin/src/View/Sync/HtmlView.php` | V |
| `admin/src/View/Unblock/HtmlView.php` | V |
| `admin/src/View/Ungroup/HtmlView.php` | V |
| `admin/src/View/Usage/HtmlView.php` | V |
| `admin/src/View/ViewTrait.php` | V |
| **admin/tmpl/** | |
| `admin/tmpl/access/default.php` | V |
| `admin/tmpl/addgroups/default.php` | V |
| `admin/tmpl/addgroups/progress.php` | V |
| `admin/tmpl/addgroups/result.php` | V |
| `admin/tmpl/addgroups/review.php` | V |
| `admin/tmpl/age/default.php` | V |
| `admin/tmpl/age/list.php` | V |
| `admin/tmpl/anonymize/default.php` | V |
| `admin/tmpl/anonymize/progress.php` | V |
| `admin/tmpl/anonymize/result.php` | V |
| `admin/tmpl/anonymize/review.php` | V |
| `admin/tmpl/compare/default.php` | V |
| `admin/tmpl/compare/progress.php` | V |
| `admin/tmpl/compare/result.php` | V |
| `admin/tmpl/compare/review.php` | V |
| `admin/tmpl/delete/default.php` | V |
| `admin/tmpl/delete/result.php` | V |
| `admin/tmpl/delete/review.php` | V |
| `admin/tmpl/diagnose/default.php` | V |
| `admin/tmpl/explain/default.php` | V |
| `admin/tmpl/export/default.php` | V |
| `admin/tmpl/handover/default.php` | V |
| `admin/tmpl/handover/progress.php` | V |
| `admin/tmpl/handover/result.php` | V |
| `admin/tmpl/handover/review.php` | V |
| `admin/tmpl/handover/select.php` | V |
| `admin/tmpl/home/default.php` | V |
| `admin/tmpl/inactive/default.php` | V |
| `admin/tmpl/inactive/result.php` | V |
| `admin/tmpl/inactive/review.php` | V |
| `admin/tmpl/move/default.php` | V |
| `admin/tmpl/move/result.php` | V |
| `admin/tmpl/move/review.php` | V |
| `admin/tmpl/profile/default.php` | V |
| `admin/tmpl/report/default.php` | V |
| `admin/tmpl/reports/default.php` | V |
| `admin/tmpl/schedule/default.php` | V |
| `admin/tmpl/sync/default.php` | V |
| `admin/tmpl/sync/progress.php` | V |
| `admin/tmpl/sync/result.php` | V |
| `admin/tmpl/sync/review.php` | V |
| `admin/tmpl/unblock/default.php` | V |
| `admin/tmpl/unblock/result.php` | V |
| `admin/tmpl/unblock/review.php` | V |
| `admin/tmpl/ungroup/default.php` | V |
| `admin/tmpl/ungroup/groups.php` | V |
| `admin/tmpl/ungroup/result.php` | V |
| `admin/tmpl/ungroup/review.php` | V |
| `admin/tmpl/usage/default.php` | V |
| **media/** | |
| `media/js/admin.js` | S |
| **modules/** | |
| `modules/mod_usertools/services/provider.php` | D |
| `modules/mod_usertools/src/Dispatcher/Dispatcher.php` | R |
| `modules/mod_usertools/tmpl/default.php` | V |
| `modules/mod_usertools_groups/services/provider.php` | D |
| `modules/mod_usertools_groups/src/Dispatcher/Dispatcher.php` | R |
| `modules/mod_usertools_groups/src/Helper/MembershipHelper.php` | R |
| `modules/mod_usertools_groups/src/Service/GroupTree.php` | R |
| `modules/mod_usertools_groups/tmpl/default.php` | V |
| **package/** | |
| `package/script.php` | A |
| **plugins/** | |
| `plugins/system/usertools/services/provider.php` | D |
| `plugins/system/usertools/src/Extension/Usertools.php` | A |
| `plugins/task/usertools/services/provider.php` | D |
| `plugins/task/usertools/src/Extension/Usertools.php` | A |
| **scripts/** | |
| `scripts/build.php` | R |

**S** does not indicate incorrect documentation. It marks contracts to recheck if the underlying logic changes. No test or manifest files were modified, and edits to executable source files are limited to comments.
