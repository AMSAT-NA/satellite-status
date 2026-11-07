# Knowledge Bundle Update Log

## 2026-11-07
* **Update**: [agents/changelog-convention](/agents/changelog-convention.md): CHANGELOG entries are
  dated by **merge date** (UTC), not the date they were written.
* **Creation**: Added [runbooks/2026-11-04-cutover](/runbooks/2026-11-04-cutover.md), the as-run record of
  the amsat.org/status to status.amsat.org cutover: timeline, deviations from the plan (the migration's
  1970-dated-row failure and recovery, the php.ini hotfix #51, #30's late corrections commits, the #49
  rebase), and lessons. The checklist used on the night is alongside it as a PDF.

## 2026-10-27
* **Update**: [agents/changelog-convention](/agents/changelog-convention.md) — entries now
  reference the **PR number** (required) instead of a commit SHA. Squash merges make the final
  SHA unknowable when the entry is written, and a branch SHA isn't on `main` afterwards. Also
  made "every PR gets an entry unless explicitly told otherwise" explicit, and documented how
  to handle two open PRs that both append to the CHANGELOG.

## 2026-08-01
* **Update**: [agents/ci-pipeline](/agents/ci-pipeline.md) — corrected the stale "CD is not yet
  implemented" note; CD has existed and deployed to production since. Points to `CD_NOTES.md` /
  `RESTRUCTURE_NOTES.md` at the repo root as the CD system of record.
* **Creation**: Added [agents/deploy-versioning](/agents/deploy-versioning.md) — design decisions
  for surfacing the deployed commit SHA and deploy timestamp on the frontend and API (Issue #20).
* **Creation**: Added [agents/changelog-convention](/agents/changelog-convention.md) — the
  convention agents must follow when updating the root `CHANGELOG`, and why it's a documented
  convention rather than a CI-enforced check.

## 2026-06-17
* **Initialization**: Created the `docs/` knowledge bundle and established the OKF bundle structure.
* **Creation**: Added [agents/ci-pipeline](/agents/ci-pipeline.md) — CI pipeline design decisions following migration from GitLab to GitHub Actions.
