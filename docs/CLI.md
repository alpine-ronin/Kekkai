# CLI

Every command takes `--root <dir>` (default: `$CLAUDE_PROJECT_DIR`, else the current directory).

## kekkai init

Writes a commented `kekkai.yaml`. Leaves an existing one as it is.

## kekkai install claude [--write] [--binary <command>]

Prints the hooks for `.claude/settings.json` and the server for `.mcp.json`; with `--write`, merges them into those
files. See [HOOKS.md](HOOKS.md).

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

Exit code 0 when the review passed, 1 when it failed, 2 on an error (not a git repository, invalid configuration).

In CI, the scope file is not there (it is never committed): the review then reports the changes as made without a
scope, and fails only on the checks. To check a branch against a scope in CI, declare it in the job first:

```bash
vendor/bin/kekkai scope declare pricing -m "$PR_TITLE"
vendor/bin/kekkai check --base origin/main
```

## kekkai hook pre-tool-use | stop [--no-checks]

The Claude Code hooks: JSON on stdin, JSON on stdout. See [SPEC.md, Claude Code hooks](SPEC.md#claude-code-hooks).

## kekkai serve

The MCP server on stdio. See [MCP-TOOLS.md](MCP-TOOLS.md).
