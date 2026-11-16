# Changelog

## 0.1.0 (unreleased)

### Added
- Scope of a task: declared by the agent (`declare_scope`) or the user (`kekkai scope declare`), from paths, globs and
  zones of `kekkai.yaml`; widened with a reason kept for the reviewer (`widen_scope`, `kekkai scope widen`).
- Claude Code `PreToolUse` hook: refuses `Edit`, `Write`, `MultiEdit` and `NotebookEdit` outside the scope, asks the
  user for protected files, never lets the agent edit `kekkai.yaml`, `.kekkai/` or `.git/`.
- Claude Code `Stop` hook and `kekkai check`: the files changed since a git revision outside the scope, and the checks
  of `kekkai.yaml` run on the diff. Sends the agent back once when they fail.
- MCP server (`kekkai serve`): `declare_scope`, `widen_scope`, `show_scope`, `check_changes`.
- `kekkai init` and `kekkai install claude [--write]`.
