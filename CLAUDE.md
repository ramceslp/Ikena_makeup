# Ikena — Working Agreements

Project conventions for anyone (human or agent) writing code here.
For architecture and the API contract, see `ARCHITECTURE.md` — this file is only about **how work gets verified**.

## Why this file exists

Three defects reached review in this repo with the **entire test suite green**:

| Defect | What the tests did | What they missed |
|---|---|---|
| `fputcsv` `$escape` corrupted CSV rows (PR #82) | Asserted on well-formed names | A name containing a backslash-quote |
| `isFullCostCoverage` rounded before comparing (PR #84) | Asserted round percentages | 99.5%, which rounds to 100 |
| `path` validated before normalization (PR1b, visitor-analytics) | Asserted 255 and 256 chars | A path containing a query string |

None of these were caused by carelessness. In each case **the same understanding of the problem produced both the code and its tests**, so the case the author never imagined while writing the code was also the case they never imagined while writing the test. Green CI is evidence that the cases you thought of pass. It is not evidence of correctness.

The two rules below exist to break that symmetry. They are cheap. Follow them.

## Rule 1 — A test guarding a known trap must be proven RED

Any test whose purpose is to protect against a specific known failure mode must be **demonstrated failing against the naive implementation** before it is accepted.

Write the wrong version on purpose. Run the test. Watch it fail, and read the failure message to confirm it fails for the *right reason*. Then write the correct version.

```
# Real example — VisitorEvent::scopeReportable()
1. Implement the naive `whereNotIn('user_id', $staffIds)`
2. Run: test_anonymous_events_survive_the_staff_exclusion
3. Observe: "Failed asserting that false is true"   ← the test has teeth
4. Replace with the NULL-safe `whereNull(...)->orWhereNotIn(...)`
5. Re-run: green
```

**A test you have never seen fail is decoration, not verification.** This costs about two minutes and it is the only way to know the assertion would actually catch the bug it claims to guard.

Watch for tests that pass for accidental reasons. The scope trap above only bites when a staff user exists in the fixture — Laravel compiles `whereNotIn` with an **empty** array as `1 = 1`, which matches everything, so an empty staff list lets the naive version pass by luck.

## Rule 2 — Repeatable classes of mistake get a guard test, not a case

When a mistake could plausibly recur in code that does not exist yet, do not add one more example test. Add a test that fails whenever **anything** in that class goes wrong.

Existing guard tests in this repo, and what each makes structurally impossible:

| Guard | Prevents |
|---|---|
| `RevenueSourceIsolationTest` | Any file under `app/Reports/` touching the orders table for money |
| `AnalyticsMoneyIsolationGuardTest` | The same, for `app/Analytics/` |
| `QueryReportableGuardTest` | A future analytics query object forgetting `reportable()` (bot/staff exclusion) |
| `MysqlSuiteCoverageTest` | A testsuite declared in `phpunit.mysql.xml` but never invoked by the CI workflow |

A guard test costs one file and catches the mistake nobody thought to test for, forever. It is the highest-leverage testing available in this codebase — prefer it over a third example of the same assertion.

Note that `RevenueSourceIsolationTest` scans **raw file text**, including comments and docblocks. Any new file under `app/Reports/` must avoid the literal string `'orders'` even inside prose.

## Rule 3 — Review reads the diff, not the report

Before anything is pushed, the production diff is read against the real schema by someone who did not write it. The reviewer's question is **"which case is absent?"**, not "is what is here correct?".

All three defects in the table above were found this way, and none were found by running tests.

Agents implementing a slice **commit locally and do not push or open PRs** until that review has happened.

## Known silent failure modes in this codebase

Check these explicitly. Each one fails without an error message.

- **Queue**: `config/queue.php` defaults to `database` and **no worker runs**, while `phpunit.xml` and `phpunit.mysql.xml` both force `sync`. Anything dispatched to a job passes every test and silently never executes in production. Pin synchronous writes with `Queue::fake()`.
- **`NOT IN` and NULL**: SQL evaluates `NOT IN` to NULL for a NULL column, and a WHERE clause discards NULL rows. A bare `whereNotIn` on a nullable column silently drops every NULL row. Use a nested `whereNull(...)->orWhereNotIn(...)`.
- **CI testsuite wiring**: `.github/workflows/tests.yml` names testsuites explicitly. A suite added to `phpunit.mysql.xml` without editing the workflow simply never runs — no error, no skip notice. (`MysqlSuiteCoverageTest` now guards this.)
- **Validation order**: validate the value that will actually be **stored**. Normalizing after validation (or vice versa) rejects valid input or stores invalid input. Normalize in `prepareForValidation()`.
- **Rounding before comparing**: never derive a boolean gate from a rounded display value. Compare the underlying units; round only for display, and floor rather than round when a badge exists *because* a value is partial.
- **Public endpoints and column widths**: every client-supplied string written to a bounded column needs an explicit `max:` rule, or hostile input turns into a 500 under MySQL strict mode.
- **`ONLY_FULL_GROUP_BY`**: the real-MySQL CI job enforces it and SQLite does not. Select only grouped columns and aggregates; hydrate labels in PHP afterwards.

Add to this list whenever a new one is found. It is injected into implementation prompts, so entries here become checks that actually get performed.

## Commits

Conventional commits. **Never** add `Co-Authored-By` or any AI attribution to a commit message or PR body.
