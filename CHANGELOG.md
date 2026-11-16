# Changelog

Kekkai follows [semantic versioning](https://semver.org). Before 1.0.0, a minor version (0.2.0) may change the
configuration, the CLI or the MCP tools; each change is listed here.

## 0.1.0 (2026-11-16)

The first release.

### Scope
- A task's scope: an intent and paths, globs or zones of `kekkai.yaml`, declared by the agent (MCP `declare_scope`)
  or the user (`kekkai scope declare`), widened with a reason kept for the reviewer (`widen_scope`,
  `kekkai scope widen`), cleared by the user (`kekkai scope clear`).
- A declared scope cannot be replaced by the agent, only widened; a scope covering the whole project is refused.

### Guard
- Claude Code `PreToolUse` hook on `Edit`, `Write`, `MultiEdit`, `NotebookEdit`: refuses an edit outside the scope
  with the scope and what to do, asks the user for protected files and paths outside the project, never lets the agent
  edit `kekkai.yaml`, `.kekkai/` or `.git/`, and never answers `allow`.
- `without-scope` (`allow`, `ask`, `deny`), `outside-scope` (`ask`, `deny`), `allow-widen`, `protected`,
  `always-allowed` and `zones` in `kekkai.yaml`, validated: an unknown key is an error.

### Review
- `kekkai check`, the MCP tool `check_changes` and the Claude Code `Stop` hook review the files changed since a git
  revision (shell writes included) and run the checks of `kekkai.yaml` with `$KEKKAI_CHANGED_FILES`. The stop hook
  sends the agent back once when the review fails.
- `--format json` (docs/schemas/report.schema.json); coloured marks in a terminal.

### Setup and distribution
- `kekkai init`, `kekkai install claude [--write]`, `kekkai doctor`.
- Binaries needing no PHP (Linux x86_64 and arm64, macOS arm64) and `kekkai.phar` (PHP 8.3+), with `SHA256SUMS` and
  build attestations; `install.sh` for `curl | sh`; `kekkai self-update` with the same checks; the `edge`
  pre-release for every push to main.
