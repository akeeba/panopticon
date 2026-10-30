# Task params authority

Task `params` are stored data, written by whoever created the task. A task type that acts on sites or users named in its
params must not trust them.

## The two layers (gh-1089)

1. **Creation, JSON API:** `AbstractApiHandler::guardTaskParams()`, called by `Api\V1\Task\Add` and `Modify`. Non-super
   users need `panopticon.admin` on every ID in `params.sites`, and `initiating_user` is overwritten with the caller's
   ID. Super users are unrestricted.
2. **Execution, in the task:** `ExtensionInstall` re-checks, per site, that `initiating_user` has `panopticon.admin` or
   `panopticon.run` (the same rule as `Controller\Extensioninstall`, see `userMayInstallOnSite()`). A site that fails is
   marked `failed` in the results and skipped. An `initiating_user` of 0 or missing is a system task and is trusted.

**Why:** the API used to store `params` as NULL, so they never took effect. Fixing that made API-supplied `sites`, `url`
and `initiating_user` live and opened a privilege escalation for non-super site admins. The task-level re-check is what
still protects you if some creation route skips the guard.

**How to apply:** when adding a task type that takes site IDs or a user from its params, put a per-site check like
`userMayInstallOnSite()` in the task, and make `guardTaskParams()` validate the new key if it selects sites.

## Decided on purpose

- `guardTaskParams()` forces `initiating_user` only when `sites` or `initiating_user` is present. Forcing it for every
  non-super payload adds nothing: without `sites`, `extensioninstall` does nothing, and a future parameter with another
  name would bypass it anyway.
- "`initiating_user` 0 means system task" is a fail-open default, reachable only by super users, who can already install
  anywhere. A server-side system-task marker (a new DB column) was judged disproportionate.

## Tests

`tests/Integration/Api/V1/Tasks/ParamsAuthorityTest.php` and `tests/Integration/Task/ExtensionInstallAuthorityTest.php`
build a real non-super site admin through a group privilege and two sites. Copy their fixtures.
