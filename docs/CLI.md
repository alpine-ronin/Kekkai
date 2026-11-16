# CLI

Every command takes `--root <dir>` (default: `$CLAUDE_PROJECT_DIR`, else the current directory).

## kekkai init

Writes a commented `kekkai.yaml`. Leaves an existing one as it is.

## kekkai install claude [--write] [--binary <command>]

Prints the hooks for `.claude/settings.json` and the server for `.mcp.json`; with `--write`, merges them into those
files. See [HOOKS.md](HOOKS.md).

## kekkai doctor

Checks what Kekkai needs in this project, one line each, with the command that fixes it:

| Line | ✗ when | ! when |
|---|---|---|
| Version and installation (binary, phar, composer, source) | | |
| Git repository | not a git repository | |
| `kekkai.yaml` | invalid | missing (the defaults apply) |
| Each check's command | | not on the `PATH` |
| `PreToolUse` hook in `.claude/settings.json` | missing | |
| `Stop` hook | | missing |
| `kekkai` server in `.mcp.json` | | missing |
| The scope | | |

Exit code 1 when a line is ✗.

## kekkai scope

```bash
kekkai scope                                              # show
kekkai scope declare catalog config/services.yaml -m "Add a price to products"
kekkai scope widen src/Shared/Money.php -m "Prices are rounded by Money"
kekkai scope clear
```

Declaring from the CLI replaces any current scope. Widening from the CLI works whatever `allow-widen` says.

## kekkai check

```bash
kekkai check                       # since HEAD, with the checks
kekkai check --base origin/main    # the whole branch, in CI
kekkai check --no-checks           # the scope only
kekkai check --format json         # docs/schemas/report.schema.json
```

Exit code 0 when the review passed, 1 when it failed, 2 on an error (not a git repository, an unknown revision, an
invalid configuration). In a terminal, the marks are coloured (`NO_COLOR` and `FORCE_COLOR` are honoured).

In CI, the scope file is not there (it is never committed): the review then reports the changes as made without a
scope, and fails only on the checks. To check a branch against a scope in CI, declare it in the job first:

```bash
vendor/bin/kekkai scope declare pricing -m "$PR_TITLE"
vendor/bin/kekkai check --base origin/main
```

## kekkai self-update [version | edge] [--check] [--no-attestation]

Alias `kekkai update`. Downloads the release's file for this installation (the binary for this system, or
`kekkai.phar`), checks it against `SHA256SUMS` and, with the GitHub CLI logged in, its attestation, then puts it in
place of the running one. `--check` only says whether a newer release exists (exit code 1 when one does).
Installed with Composer or run from a clone, it says how to update instead. See [INSTALL.md](INSTALL.md#updating).

## kekkai hook pre-tool-use | stop [--no-checks]

The Claude Code hooks: JSON on stdin, JSON on stdout. See [SPEC.md, Claude Code hooks](SPEC.md#claude-code-hooks).

## kekkai serve

The MCP server on stdio. See [MCP-TOOLS.md](MCP-TOOLS.md).
