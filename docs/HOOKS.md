# Claude Code

Kekkai plugs into Claude Code three ways: an MCP server (the agent declares and widens its scope), a `PreToolUse`
hook (edits are checked before they happen) and a `Stop` hook (the diff is reviewed before the agent finishes).

## Setup

```bash
vendor/bin/kekkai install claude           # prints the configuration
vendor/bin/kekkai install claude --write   # merges it into .claude/settings.json and .mcp.json
```

`--write` adds to the existing files without removing anything; running it twice changes nothing. Restart Claude
Code, then approve the `kekkai` server (`/mcp` shows it).

What it writes, when Kekkai is installed with Composer in the project:

```json
{
    "hooks": {
        "PreToolUse": [
            {
                "matcher": "Edit|Write|MultiEdit|NotebookEdit",
                "hooks": [{"type": "command", "command": "\"$CLAUDE_PROJECT_DIR\"/vendor/bin/kekkai hook pre-tool-use"}]
            }
        ],
        "Stop": [
            {
                "hooks": [{"type": "command", "command": "\"$CLAUDE_PROJECT_DIR\"/vendor/bin/kekkai hook stop", "timeout": 900}]
            }
        ]
    }
}
```

```json
{"mcpServers": {"kekkai": {"command": "sh", "args": ["-c", "\"$CLAUDE_PROJECT_DIR\"/vendor/bin/kekkai serve"]}}}
```

Installed elsewhere, the command is `kekkai` when it is on the `PATH`, else its absolute path. `--binary` sets it.

Commit both files: the team then works under the same rules.

## A task, step by step

1. You ask: *"add a price to products"*. No scope yet.
2. The agent tries to edit `src/Catalog/Product.php`. With `without-scope: ask`, you are asked, and the prompt says
   the agent should call `declare_scope` first. With `deny`, the edit is refused and the agent reads that reason
   itself.
3. The agent calls `declare_scope` with `["catalog"]`. Edits in `src/Catalog/` and `tests/Catalog/` go through
   Claude Code's normal permissions.
4. It tries `src/Billing/Invoice.php`. Refused: the agent reads the scope and that it may call `widen_scope` with a
   reason, or keep to the scope.
5. It runs `sed -i` on `src/Shared/Money.php` through Bash. The edit hook doesn't see it.
6. It stops. The stop hook reviews the diff: `src/Shared/Money.php` is outside the scope, the review fails, and the
   agent is sent back with the report: revert, or widen with a reason.
7. It widens, and the checks pass: it stops. `kekkai check` in your terminal or in CI shows the same report, with the
   widening and its reason.
8. You review and commit, then `kekkai scope clear` before the next task.

## Several agents

The scope is one file per checkout. Two agents in the same directory share it: give each its own git worktree
(`git worktree add ../shop-pricing`), which is also what keeps their diffs apart.

## Other agents

The MCP server and `kekkai check` work with any agent. Without an edit hook, nothing stops the edit before it happens:
run `kekkai check` before accepting the work, or in CI with `--base origin/main`.
