# Knowledge Bundle Update Log

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
