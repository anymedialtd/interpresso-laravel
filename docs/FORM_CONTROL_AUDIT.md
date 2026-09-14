# Form and interactive control audit

Audit date: 2026-09-13. Scope: all 24 files under `resources/views/`, all 37 web routes in `routes/web.php`, all web controllers and the five controller actions in `routes/api.php`. Browser-generated controls in `resources/js/modules/` are included.

There are 16 literal `<form>` declarations in nine view files. Loops and shared partials expand them into the field, filter and row forms listed below. There are eight concrete `data-toggle` targets, two native selects, and one native dialog. Repeated table rows and options use the same control implementations.

**Coverage in this inventory means a normal executable test is present. It does not mean the browser test passed in this environment.** Localhost binding and Chromium startup are blocked by the sandbox. See verification results at the end.

## Global guard and fixture isolation

Every spec imports the automatic `test` fixture from [helpers.js](../tests/e2e/helpers.js). The guard is attached before page creation and retains failures across navigation, redirects, popups and secondary tabs. It fails teardown for:

- Any `pageerror`, including an exception before module initialization completes.
- Any `console.error`.
- Any `securitypolicyviolation`, with document, directive and blocked source recorded.
- Any response with status 400 or greater from the configured application origin, including documents, assets and fetches.

There are no URL, status or console allowlists. Failures produce a `browser-health` JSON attachment. [health-guard.spec.js](../tests/e2e/health-guard.spec.js) runs isolated deliberate failures and a healthy control in a child runner, and checks that each expected failure was actually caused by the guard. These canaries are not application defects or skipped tests. [browser-health-imports.test.mjs](../tests/js/browser-health-imports.test.mjs) prevents a new spec from importing the unguarded Playwright test fixture.

Intentional authorization denials use Playwright's session-sharing request client and PHPUnit, where their status is asserted explicitly. The browser error/retry case injects an invalid successful JSON response; actual service HTTP 502 behavior is also checked in PHPUnit and the JavaScript HTTP tests. This keeps normal browser traffic subject to the guard without suppressing expected errors globally.

Every application spec resets its disposable SQLite database, file cache, session directory and language files before login. Persistent file caching makes login throttling observable across real HTTP requests. The E2E-only provider is inert unless `INTERPRESSO_E2E=1`; it isolates language/cache/session paths, defaults to sync with bulk HTTP refusal, lets success tests select database queueing and a separate CLI worker, stores mail in memory, and substitutes only the external AI translation service. Imports and exports use actual files. Model exports update a real disposable JSON column. Fixture scenarios are also exercised through HTTP by PHPUnit.

## Login and shared layout

Sources: [login](../resources/views/login.blade.php), [layout](../resources/views/layouts/app.blade.php), [LoginController](../src/Controllers/LoginController.php).

| Control / form | Route or behavior | Browser coverage |
| --- | --- | --- |
| Email, Password and Sign in POST | `interpresso.login.submit`; valid login, invalid credentials, required inputs, IP throttling after ten attempts | `auth.spec.js` |
| Remember me checkbox | Persistent authentication cookie; login after session cookie removal | `auth.spec.js` |
| Logout POST / submit button | `interpresso.logout`; invalidates authentication, including remembered login; protected screen redirects afterward | `auth.spec.js`, `security-headers.spec.js`, `shared-controls.spec.js` |
| Brand link | `interpresso.languages` | `shared-controls.spec.js` |
| Languages, Manual, Translators, Settings navigation links | Their matching index routes; administrator links hidden from regular translators | `screens.spec.js`, `permissions.spec.js`, `shared-controls.spec.js` |
| Open main menu button, `mobile-menu` target | Opens/closes at mobile width; all links and logout work | `shared-controls.spec.js` |
| Light / Dark button | Changes theme; cookie and storage persist; saved theme already in HTML and correct at first paint while the application module is held | `security-headers.spec.js` |
| Theme without JavaScript | System CSS fallback; saved cookie overrides system | `security-headers.spec.js` |

Theme tests also cover legacy localStorage migration, cookie precedence, system preference changes and unavailable localStorage. A first visit with only a legacy storage preference still migrates when the external module loads, as documented; the no-flash assertions concern persisted server-readable cookie preferences.

## Languages

Sources: [languages](../resources/views/languages.blade.php), [table](../resources/views/component/table.blade.php), [LanguageController](../src/Controllers/LanguageController.php).

| Control / form | Route or outcome | Browser coverage |
| --- | --- | --- |
| Search input, debounced GET and Search submit | `interpresso.languages`; name/code/native-name search; clear restores rows | `languages.spec.js`, `pagination.spec.js` |
| Add Language GET submit | Opens `?create=1` | `languages.spec.js` |
| Language native select, Add POST submit | `interpresso.languages.store`; new language survives reload; required selection; existing codes excluded | `languages.spec.js` |
| Add form Close link | Discards selection without creating anything | `languages.spec.js` |
| View row link | `interpresso.translations` for the selected language | `translations.spec.js`, `permissions.spec.js`, `bulk-actions.spec.js` |
| Delete row action form | `interpresso.languages.delete`; language and its translations disappear, including after re-adding the language | `bulk-actions.spec.js` |
| Import Languages action form | `interpresso.languages.import-languages`; discovers the fixture's Italian directory | `bulk-actions.spec.js` |
| Import Translations action form | `interpresso.languages.import-translations`; a worker persists real PHP and JSON file contents; sync shows the CLI command without a batch or writes | `bulk-actions.spec.js`, `queue-refusal.spec.js` |
| Find Missing Translations action form | `interpresso.languages.find-missing`; creates six missing German counterparts | `bulk-actions.spec.js` |
| Approve (All Languages) Translations action form | `interpresso.languages.approve`; persisted approval in English and German | `bulk-actions.spec.js` |
| Export All Languages action form | `interpresso.languages.export`, `exportOnlyModels=0`; exports approved eligible rows to English and German files | `bulk-actions.spec.js` |
| Export All Translated Models variant | Same route, `exportOnlyModels=1`; updates both locales of a real model JSON column; file rows stay unexported; no-work feedback also checked | `bulk-actions.spec.js` |
| Delete running Batch (Jobs) action form | `interpresso.languages.cancel-jobs`; cancels a real seeded batch and job; subsequent approval can run | `bulk-actions.spec.js` |
| Previous, Next and numbered page links | Preserve search; persisted page selection | `pagination.spec.js` |

## Translations

Sources: [translations](../resources/views/translations.blade.php), [table](../resources/views/component/table.blade.php), [TranslationController](../src/Controllers/TranslationController.php).

| Control / form | Route or outcome | Browser coverage |
| --- | --- | --- |
| Search input, debounced GET and Search submit | `interpresso.translations`; key/current-content search; clear restores rows; combines with filters | `translations.spec.js`, `translation-controls.spec.js`, `pagination.spec.js` |
| Example Language native select | Selected permitted example appears in the modal | `translation-controls.spec.js` |
| Type dropdown, PHP / JSON / Model checkboxes and Apply GET submit | Any selected type matches; multiple selections, removing selections, reload and explicit Apply | `translation-controls.spec.js` |
| Updated by dropdown, translator checkboxes and Apply GET submit | Distinct updater sets; multiple selections, removing selections, reload and Apply | `translation-controls.spec.js` |
| Approved by dropdown, translator checkboxes and Apply GET submit | Distinct approver sets; multiple selections, removing selections, reload and Apply | `translation-controls.spec.js` |
| State Filters dropdown | Opens the five state forms and reopens after submission | `translations.spec.js` |
| Needs Translation GET submit | All -> true -> false -> all, exact matching row sets, bookmarked URLs and reload | `translations.spec.js` |
| Approved GET submit | All -> true -> false -> all, exact matching row sets, bookmarked URLs and reload | `translations.spec.js` |
| Updated GET submit | All -> true -> false -> all, exact matching row sets, bookmarked URLs and reload | `translations.spec.js` |
| Is Vendor GET submit | All -> true -> false -> all, exact matching row sets, bookmarked URLs and reload | `translations.spec.js` |
| Exported GET submit | All -> true -> false -> all, exact matching row sets, bookmarked URLs and reload | `translations.spec.js` |
| Translate row button | `interpresso.translations.modal`; opens the native dialog with the actual key/value | `translations.spec.js`, `translation-controls.spec.js`, `async-modal.spec.js` |
| Translation textarea and Update Translation POST submit | `interpresso.translations.update`; persists draft and previous value; works for assigned non-admin | `translations.spec.js`, `permissions.spec.js`, `async-modal.spec.js` |
| Close modal button | Discards edits; restores trigger focus; reopening after reload shows saved value | `translation-controls.spec.js` |
| Escape / backdrop dismissal | Same discard/focus behavior | `translation-controls.spec.js` |
| Translate with OPEN AI button | `interpresso.translations.suggest`; conditional presence, busy state, draft-only result, concurrent-edit protection, failure/retry | `async-modal.spec.js`, `translation-controls.spec.js` |
| Use suggestion button | Explicitly replaces a kept draft; Update saves it across reload | `async-modal.spec.js` |
| Update & auto-translate others POST submit | `interpresso.translations.update-all` via submitter `formaction`; persists root and German counterpart; hidden outside root/admin conditions | `translation-controls.spec.js`, authorization PHPUnit tests |
| Per-language example `details` / `summary` controls | Expands and collapses each actual example | `translation-controls.spec.js` |
| Approve row action form | `interpresso.translations.approve`; approval survives reload, previous draft state clears | `translation-controls.spec.js` |
| Request translation row action form | `interpresso.translations.request`; request survives reload | `translation-controls.spec.js` |
| Remove translation request row action form | `interpresso.translations.restore-request`; inverse transition survives reload | `translation-controls.spec.js` |
| Restore row action form | `interpresso.translations.restore`; previous value replaces the draft after reload | `translation-controls.spec.js` |
| Approve ({language}) Translations action form | `interpresso.translations.approve-all`; all current-language rows approved; second invocation reports no work | `translation-controls.spec.js` |
| Export Language action form | `interpresso.translations.export`, `exportOnlyModels=0`; actual eligible file output and exported row state | `bulk-actions.spec.js` |
| Export Translated Models variant | Same route, `exportOnlyModels=1`; changes only English in the real JSON column; no-work feedback also checked | `bulk-actions.spec.js` |
| Previous, Next and numbered page links | Preserve search, all three multi-selects and all five state filters | `pagination.spec.js` |

## Translators

Sources: [translators](../resources/views/translators.blade.php), [TranslatorController](../src/Controllers/TranslatorController.php).

| Control / form | Route or outcome | Browser coverage |
| --- | --- | --- |
| Search input, debounced GET and Search submit | `interpresso.translators`; filters and clears accounts | `translators.spec.js`, `pagination.spec.js` |
| Create Translator GET submit | Opens `?create=1` | `translators.spec.js` |
| Create form: Email, Phone, First Name, Last Name, Password, Password Confirmation | `interpresso.translators.store`; saves profile; validation retains editable values and recovers | `translators.spec.js` |
| Languages assignment dropdown and every language checkbox | Dropdown must become visible; checked selections are submitted, retained after reload and can be removed | `translators.spec.js` |
| Is Administrator switch | Saves administrator permission; account can subsequently open Settings without assignments | `translators.spec.js` |
| Create submit / Close link | Creates persisted account or discards input | `translators.spec.js` |
| Edit row link | `interpresso.translators.edit`; loads existing values | `translators.spec.js` |
| Edit form profile fields, Update submit / Close link | `interpresso.translators.update`; profile and assignments persist, or edits are discarded | `translators.spec.js` |
| Update Password link | Opens `?password=1` | `translators.spec.js` |
| New Password and confirmation, Update Password submit | `interpresso.translators.password`; mismatch recovers; old password rejected, new password logs in | `translators.spec.js` |
| Password form Close link | Discards password change and returns to profile editor | `translators.spec.js` |
| Send pending translations notification action form | `interpresso.translators.notify`; conditional presence; assigned recipient sees pending count after a new login and reload | `notifications.spec.js` |
| Delete row action form | `interpresso.translators.delete`; account absent after reload, primary admin retained | `translators.spec.js` |
| Filter by languages dropdown, checkboxes, Apply GET submit | Matches all selected assignments; combines with search, clears and persists | `translators.spec.js`, `pagination.spec.js` |
| Previous, Next and numbered page links | Preserve search and assignment filter | `pagination.spec.js` |

## Settings

Source: [settings](../resources/views/settings.blade.php), [SettingController](../src/Controllers/SettingController.php), [UpdateSettingFieldRequest](../src/Requests/UpdateSettingFieldRequest.php).

Each row below is a separate POST form to `interpresso.settings.update` with its own named input and Save submit button. `settings.spec.js` checks JavaScript autosave and reload, and **every individual fallback Save button with JavaScript disabled**. The toggles are checked in both directions while other fields remain unchanged.

| Field / control | Covered behavior |
| --- | --- |
| `enable_multi_host` checkbox | Requires saved Domains; invalid enable reports an error without persisting; valid enable and disable persist |
| `domains` text input | Saves independently; required only while enabled; cannot clear while enabled; clears after disabling |
| `db_loader` checkbox | Both saved states; export button variants use it |
| `import_vendor` checkbox | Both saved states |
| `enable_pending_notifications` checkbox | Both saved states; notification button visibility and delivery |
| `enable_automatic_pending_notifications` checkbox | Both saved states |
| `enable_open_ai_translations` checkbox | Both saved states; editor button visibility |
| `import_only_from_root_language` checkbox | Both saved states |
| `allow_deleting_languages` checkbox | Both saved states; deletion permission remains enforced by the server |

## Manual, notifications, progress and reusable partials

| Source / control | Outcome and coverage |
| --- | --- |
| `manual.blade.php`: quick links Languages, Translators, Settings | Real heading targets and scrolling: `shared-controls.spec.js` |
| `manual.blade.php`: every generated section link and every internal anchor | Iterates all rendered fragment links, checks unique target, URL hash and viewport: `shared-controls.spec.js` |
| `partials/notifications.blade.php`: Notifications dropdown | Opens/closes and shows real unread messages: `notifications.spec.js` |
| `modules/notifications.js`: generated Mark as read button | `interpresso.notifications.read`; removes only selected notification, persists after reload: `notifications.spec.js` |
| Mark all as read button | `interpresso.notifications.read-all`; all own notifications remain read after reload and refreshed poll: `notifications.spec.js` |
| `partials/batch-progress.blade.php`: progress indicator (no submit control) | Real active/cancelled batch: `bulk-actions.spec.js`; visible/hidden polling, completion display and polling stop: `polling.spec.js`; HTTP progress in PHPUnit |
| `modules/toast.js`: generated Dismiss notification button | Removes toast immediately: `shared-controls.spec.js` |
| `partials/toast.blade.php`: initial toast data | Empty, malformed and null payloads cannot disable dropdowns: `shared-controls.spec.js`, `tests/js/toast.test.mjs` |
| `partials/action-form.blade.php` | All 15 action variants are listed above: six global language actions, language delete, translator delete/notify, two language-specific translation actions, and four translation row actions |
| `partials/query-fields.blade.php` | Hidden filter fields preserve independent controls and reset page on filter change: filter and pagination specs |
| `partials/pagination.blade.php` | Previous, Next, current and numbered links covered on all three tables |
| `component/select-checkbox-multiple.blade.php` | All five instances covered: translation type/updater/approver, translator assignment and translator list filter |
| `component/select-checkbox-three-states.blade.php` | All five forms covered in every state |
| `component/search.blade.php` | All three instances covered via typing/clearing and submit |
| `component/input.blade.php`, `switch.blade.php`, `button.blade.php` | Every consuming named field, switch and submit is covered above |
| `component/boolean-icon.blade.php`, `error.blade.php`, `table-h1-heading.blade.php`, `table-section.blade.php`, table headings/cells | Display-only components; no additional form, dropdown, modal or action control |

The eight concrete dropdown targets are `mobile-menu`, `notification-list`, `state-filter-options`, `filter-types-options`, `filter-updatedBy-options`, `filter-approvedBy-options`, `translator-permissions-options`, and `translator-language-filter-options`.

## HTTP coverage and authorization

The existing suite covers login, CRUD, settings validation, translator ownership, translation resolution, notification ownership and security headers. New [HttpControlCoverageTest](../tests/Feature/HttpControlCoverageTest.php) executes actual import, missing-translation, approval, file/model export, cancellation and auto-translation jobs through HTTP. It verifies saved output, unchanged unrelated data, setting field isolation, and suggestion failure without writes. New [HttpApiCoverageTest](../tests/Feature/HttpApiCoverageTest.php) covers language listing, 501-row pagination, queue state/cancellation, actual forced export after response, and all five protected API routes with missing/invalid/wrong credentials.

[HttpAuthorizationTest](../tests/Feature/HttpAuthorizationTest.php) independently enumerates all 24 admin routes, checks their middleware, and asserts 403 plus identical snapshots of languages, translations, translators, assignment pivots, settings, notifications, jobs and batches. It also tests all nine settings field URLs. Removing middleware cannot remove an endpoint from this inventory silently. Existing translation authorization tests deny mixed language/row IDs even for admins and deny non-admin access to unassigned language examples and updates.

API routes use shared-key authentication, not the administrator session. Missing/malformed keys return 422 and incorrect keys return 401, with unchanged database snapshots. The public version route remains public. No authentication or access-control behavior was changed.

## Bugs and changes

1. **Invalid suggestion response could overwrite the draft with `undefined`: fixed.** The JSON helper previously converted parse failures into `{}` and the editor accepted a missing `value`. Parsing errors now report a recoverable error, invalid suggestion values are rejected, the draft stays intact and controls remain usable. HTTP error status is retained so authentication failures still stop polling. Covered by browser retry/invalid-payload specs and JavaScript HTTP tests.
2. **The E2E harness could not prove real login rate limiting: fixed.** `CACHE_STORE=array` lost rate-limit counters between requests. E2E now uses an isolated file cache, cleared before each test. Application rate limiting itself was not changed.
3. **Import/export fixtures could inherit or alter Testbench language files: fixed.** E2E language files, cache and sessions now live under the disposable `.data` directory. Fixtures contain actual import files and model export targets. Production assets are copied without swallowing copy errors.
4. **Original uncaught toast parse regression: protected.** The existing empty/malformed-toast fix was already present. The global browser guard, deliberate startup canary, real dropdown scenarios and JavaScript regression cases now protect it.

No known unfixable application control defect was registered, so there are no `test.fixme` or skipped application cases. This is not a claim that unexecuted browser scenarios passed. The browser checks remain required before release; this repository has no GitHub Actions workflow enforcing them automatically.

## Verification results

The follow-up investigation addresses the nine reported failures below (the saved-theme case runs once for each theme). Browser diagnoses that require geometry or pointer-event inspection remain provisional because this session cannot launch Chromium. No Playwright assertion was removed or relaxed, and the automatic health guard remains active.

| Reported case | Cause and classification | Change and evidence |
| --- | --- | --- |
| `notifications.spec.js:5`, open/close and read | Application panel interaction, exact timeout cause unconfirmed. Visibility was shared between the `hidden` toggle and DaisyUI dropdown CSS. Every unchanged poll also destroyed and recreated message buttons. | The panel uses explicit positioning above the fixed button and only the `hidden` toggle for visibility. Unchanged polls retain controls. Browser confirmation remains required. |
| `notifications.spec.js:28`, pending notification | Application panel interaction is suspected; delivery is verified independently. | The real notification HTTP regression delivers the expected pending count to the assigned translator and persists marking it read. The shared panel fixes apply; the spec is unchanged. |
| `pagination.spec.js:27` | Wrong test assumption: English is not on the first page after the pagination fixture inserts earlier language codes. | `openTranslations()` uses the real language search when the requested row is absent, then follows View. All pagination and filter assertions remain. |
| `security-headers.spec.js:133`, saved light and dark | Application cookie-scope collision: Playwright's URL cookie has path `/translator/`, while the toggle writes `/translator`. Both are sent on login reload; the more specific stale cookie shadows the new choice. | Theme persistence expires the trailing-slash duplicate before writing the canonical cookie. Server-rendered theme assertions are unchanged. JavaScript regressions cover both directions, nested prefixes, root installations and unavailable storage. |
| `shared-controls.spec.js:3`, manual anchors | Application layout: the sticky index can exceed viewport height, leaving its lower links outside the viewport while scrolling the document. Heading targets are generated from the same section records as the links. | The sticky index has a viewport-based maximum height and its own vertical scroll. Every anchor assertion remains. Browser confirmation remains required. |
| `translators.spec.js:137`, create validation | Application translation-loader bug: the correct error fields rendered `validation.required` and `validation.same` in DB mode. The recovery step also left the assignment dropdown over Create. | DB mode retains Laravel's validation language path and merges validation defaults, host overrides and reviewed DB values. HTTP regressions reproduce and verify messages in both loader modes. The spec closes Languages before submitting the recovered form. |
| `translators.spec.js:178`, password validation | Application translation-loader bug: the validator's `new_password_confirmation` error rendered the untranslated `validation.same` key. | The same loader fix restores the message. The regression verifies field-level rendering and that the rejected password change leaves the hash untouched. The browser assertion is unchanged. |
| `translators.spec.js:200`, administrator switch | Wrong test interaction identified from the markup: the open assignment dropdown overlaps Update. Exact pointer-event diagnosis remains unconfirmed without the browser log. | The test closes Languages after unchecking English, then saves. It still verifies removed assignments, persisted Admin status and Settings access after logging in as the promoted translator. |

`TranslatorFormErrorsTest` explicitly selects the post-migration DB loader: ordinary in-memory Testbench startup happens before the settings table exists and can select the file loader, hiding the original validation defect. Validation fallback is limited to the framework validation group; application content and authorization rules are unchanged.

| Check | Actual result |
| --- | --- |
| `./vendor/bin/phpunit` | PASS: 270 tests, 1,317 assertions |
| `./vendor/bin/phpstan analyse --no-progress --debug` | PASS: level 10, 0 errors; debug mode runs serially because the parallel worker's local TCP listener is blocked |
| `npm run production` | PASS: production JS and CSS regenerated |
| `node --test tests/js/*.test.mjs` | PASS: 22 tests, no skips |
| `npx --no-install playwright test --list` | 115 tests discovered in 16 spec files; this is not execution |
| `./tests/e2e/seed.sh && npx playwright test` | BLOCKED: seeding succeeds; Testbench cannot bind localhost and Playwright exits before running scenarios |
| Independent Chromium launch | BLOCKED: Chromium terminates with `bootstrap_check_in ... Permission denied (1100)` / Mach port sandbox denial |
| `git diff --check` | PASS |

The server's direct diagnostic is `Failed to listen on 127.0.0.1:8099 (reason: Operation not permitted)`. No browser pass is claimed. The guard canaries and first-paint checks also still require execution in an environment that permits localhost and Chromium. No installs or commits were made.
