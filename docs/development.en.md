# Developer onboarding guide

[Español](desarrollo.md) · **English**

[Back to README](../README.en.md#development) · [Full architecture](architecture.en.md) · [Project rules](../AGENTS.md)

## Purpose

This is an entry path for someone unfamiliar with the source code. It does not replace the architecture documentation, code, tests or public Joomla APIs. Work from `main` or your change branch and verify that the description still applies; do not reconstruct business rules from AI-agent conversations.

## Suggested reading order

1. **[AGENTS.md](../AGENTS.md)**: security invariants, conventions, accessibility, bilingual documentation and required tests.
2. **[README.en.md](../README.en.md)** and **[FAQ.en.md](../FAQ.en.md)**: observable behaviour, permissions and each tool's limits.
3. **[Architecture](architecture.en.md)**: layer map, session state, persistence, partial failures and file/test matrix.
4. **[admin/src/Controller/DisplayController.php](../admin/src/Controller/DisplayController.php)** and **[admin/src/Service/Navigation.php](../admin/src/Service/Navigation.php)**: view access, categories and effective permissions.
5. **[admin/src/Model/ToolStateTrait.php](../admin/src/Model/ToolStateTrait.php)**: previews, identity, expiry, confirmations and results.
6. **[admin/src/Service/Toolkit.php](../admin/src/Service/Toolkit.php)**: service construction; **[admin/src/Service/UserDirectory.php](../admin/src/Service/UserDirectory.php)**: users, groups and session reads.

## Example walkthrough: comparing a list and blocking/creating accounts

1. **Entry and authorization**: `CompareController` uses shared `ToolControllerTrait` guards and `DisplayController` permissions. Requests changing state must validate the CSRF token and server-side authorization.
2. **Preparation and review**: `CompareModel` interprets the draft, calculates candidates via `Comparison`, `Exclusions` and `UserDirectory`, and uses `ToolStateTrait` to persist a one-use review tied to the operator.
3. **Batched execution**: the review is revalidated before writing. `CompareModel::apply()` consumes it and creates a session job. `CompareModel::step()` processes batches, checkpoints per account and can resume without duplicating an account creation if a request stops after creating it.
4. **Writes**: `AccountBlocker` and `Registration` use Joomla's user APIs; business rules and permissions are checked again. `ActionLogger` records actions and blocking destroys sessions. Account creation may send a password-set link or return a temporary password only in that response.
5. **Output**: views and templates under `admin/src/View/` and `admin/tmpl/compare/` render reviews, progress and results. `media/js/admin.js` enhances the browser flow; browser checks never replace server validation.

Follow the same path for other tools: **Controller → Model → Service → Joomla API** and separately **View → template/layout → JavaScript**. The scheduled task does not use screen models: its entry point is **[plugins/task/usertools/src/Extension/Usertools.php](../plugins/task/usertools/src/Extension/Usertools.php)** and it reuses services.

## Guarantees to locate before changing code

- **Identity and privileges**: `Exclusions`, `Operator` and `DisplayController`. Super Users and the operator performing a manual action have specific protections; group changes have additional rules.
- **Review ≠ permanent authorization**: `ToolStateTrait` stores the temporary preview, but writes revalidate permissions, user state and membership in the offered set. New candidates must not be silently added.
- **Persistence and retries**: batched manual jobs live in the session; completed changes are not automatically rolled back. `ReportStore` saves scheduled reports and undoable block batches; `ActionLogger` is a separate evidence source.
- **Personal data and anonymization**: `AccountAnonymizer` distinguishes verified identity changes, additional cleanup and privacy-plugin work that cannot be fully verified. This does not amount to deleting all external data, backups or content.
- **Presentation security**: server-side CSRF/ACL/revalidation, HTML escaping, CSV formula safety and non-cacheable sensitive responses.

Read [AGENTS.md's non-negotiable security rules](../AGENTS.md) before editing account-mutating operations.

## Tests and review

From the repository root:

```sh
composer install && npm ci
vendor/bin/phpunit
npm test
JOOMLA_PATH=/path/to/joomla-6 vendor/bin/phpstan analyse
```

End-to-end tests live in [tests/integration/run.php](../tests/integration/run.php); read its header before running them. **They create, block, modify and delete data**: only use a disposable Joomla test installation. CI covers MySQL and PostgreSQL. For documentation-only changes, check relative links and es-ES/en-GB consistency; do not claim to have run tests you have not run.

Before opening a PR: verify functional scope, find the test covering the changed rule, review side effects, and update comments, PHPDoc, architecture and user documentation that no longer describe the code. Do not narrate obvious PHP; document rationale and limitations.

## Further reading

See [full architecture](architecture.en.md), its file/test matrix, [AGENTS.md](../AGENTS.md) and the relevant module tests. The [internal documentation audit](documentation-audit.en.md) tracks findings and improvement opportunities without treating missing PHPDoc on trivial methods as an automatic defect.
