# Architecture

Kekkai is small on purpose: a hook runs on every edit of the agent, so it must start fast (about 50 ms) and depend on
little (Symfony Console and Yaml).

```text
bin/kekkai                 Symfony Console application
src/
├── Glob.php               path patterns: *, ?, **, directories
├── Path.php               paths relative to the project root, normalised without realpath()
├── Verdict.php            allow | ask | deny
├── Project.php            the root, its configuration and its scope store
├── Process.php            proc_open with a timeout, for git and the checks
├── Config/
│   ├── Config.php         kekkai.yaml: validated, defaults, guarded paths
│   └── CheckDefinition.php
├── Scope/
│   ├── Scope.php          intent, entries, widenings: declare(), widen(), covers()
│   ├── Widening.php
│   └── ScopeStore.php     .kekkai/scope.json and log.jsonl
├── Gate/
│   ├── Gate.php           the decision for one path, rules applied in order
│   └── Decision.php
├── Change/
│   └── ChangedFiles.php   git diff and untracked files since a revision
├── Check/
│   ├── Reviewer.php       changed files, decisions, checks: a Report
│   ├── Report.php         passed(), text and JSON
│   └── CheckResult.php
├── Hook/
│   └── ClaudeCode.php     PreToolUse and Stop, JSON in, JSON out
├── Mcp/
│   └── McpServer.php      JSON-RPC over stdio, four tools
└── Command/               init, scope, check, hook, serve, install
```

## Flow of an edit

```text
Claude Code ──PreToolUse JSON──▶ kekkai hook pre-tool-use
                                   │ Project::at()   reads kekkai.yaml, .kekkai/scope.json
                                   │ Gate::decide()  rules 1 to 7
                                   ▼
             ◀── nothing (allow) or {"permissionDecision": "ask" | "deny"}
```

## Flow of a stop

```text
Claude Code ──Stop JSON──▶ kekkai hook stop
                             │ stop_hook_active? → nothing
                             │ Reviewer::review('HEAD')
                             │   ChangedFiles::since()   git diff + untracked
                             │   Gate::decide() per file
                             │   checks of kekkai.yaml   sh -c, with KEKKAI_CHANGED_FILES
                             ▼
             ◀── nothing (passed) or {"decision": "block", "reason": report}
```

## Design rules

- **Pure core, thin edges.** `Glob`, `Path`, `Config`, `Scope` and `Gate` do no I/O besides reading the
  configuration: they are tested without a filesystem. Git, the scope file and the checks live in `ChangedFiles`,
  `ScopeStore` and `Reviewer`.
- **One decision function.** The edit hook, the review and the MCP server all go through `Gate::decide()`: a path is
  judged the same way before the edit and after.
- **Messages for the agent.** Every refusal says what the scope is and what to do next (`declare_scope`,
  `widen_scope`, ask the user), so the agent corrects itself in one turn instead of guessing.
- **Fail towards the user.** An error in the edit hook asks the user; an error in the stop hook lets the agent stop.
  Kekkai never silently allows an edit, and never traps the agent because of itself.

## Tests

- `tests/Unit`: patterns, paths, configuration, scope, gate.
- `tests/Integration/WorkflowTest.php`: a real git repository in a temporary directory, the whole task: no scope,
  declaration, edits in and out of scope, a change through the shell caught at stop, widening, a failing check, the
  MCP protocol.

`composer check` runs the code style, PHPStan at level max and the tests, as the CI does on PHP 8.3, 8.4 and 8.5.
