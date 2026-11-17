#!/bin/sh
# Runs a Kekkai executable (the PHAR, a binary, bin/kekkai) through a whole task in a new git repository, as an agent
# would drive it, in Claude Code's and Cursor's hook formats: setup, scope, a refused edit, a change through the shell
# caught by the review, widening, MCP.
#
#   tools/smoke.sh build/kekkai.phar [expected version]

set -eu

kekkai=$(cd "$(dirname "$1")" && pwd)/$(basename "$1")
expected=${2:-}
work=$(mktemp -d)
logs=$(mktemp -d)
trap 'rm -rf "$work" "$logs"' EXIT
cd "$work"

fail() {
    printf 'smoke: %s\n' "$1" >&2
    exit 1
}

step() {
    printf '• %s\n' "$1"
}

step "version"
version=$("$kekkai" --version)
printf '  %s\n' "$version"
[ -z "$expected" ] || printf '%s' "$version" | grep -qF "${expected#v}" || fail "expected version $expected, got: $version"

step "setup"
git init -q -b main
git config user.email smoke@example.com
git config user.name smoke
mkdir -p src/Catalog src/Billing
echo '<?php' > src/Catalog/Product.php
echo '<?php' > src/Billing/Invoice.php
"$kekkai" init > /dev/null
"$kekkai" install claude --write --binary kekkai > /dev/null
"$kekkai" install cursor --write --binary kekkai > /dev/null
git add -A && git commit -qm init

step "no scope: the edit is asked"
printf '{"tool_name":"Edit","tool_input":{"file_path":"%s/src/Catalog/Product.php"},"cwd":"%s"}' "$work" "$work" \
    | "$kekkai" hook pre-tool-use | grep -q '"permissionDecision":"ask"' || fail "an edit without scope should be asked"

step "MCP: initialize and declare_scope"
answer=$(printf '%s\n%s\n' \
    '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' \
    '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"declare_scope","arguments":{"intent":"Add a price","paths":["src/Catalog/"]}}}' \
    | CLAUDE_PROJECT_DIR="$work" "$kekkai" serve)
printf '%s' "$answer" | grep -q '"protocolVersion":"2025-06-18"' || fail "initialize: $answer"
printf '%s' "$answer" | grep -q 'Scope declared' || fail "declare_scope: $answer"

step "in scope: nothing to say"
out=$(printf '{"tool_name":"Write","tool_input":{"file_path":"%s/src/Catalog/Price.php"},"cwd":"%s"}' "$work" "$work" | "$kekkai" hook pre-tool-use)
[ -z "$out" ] || fail "an edit in scope should get no answer: $out"

step "outside the scope: denied"
printf '{"tool_name":"Edit","tool_input":{"file_path":"%s/src/Billing/Invoice.php"},"cwd":"%s"}' "$work" "$work" \
    | "$kekkai" hook pre-tool-use | grep -q '"permissionDecision":"deny"' || fail "an edit outside the scope should be denied"

step "Cursor: in scope nothing, outside denied"
out=$(printf '{"tool_name":"Write","tool_input":{"file_path":"%s/src/Catalog/Price.php"},"workspace_roots":["%s"]}' "$work" "$work" | "$kekkai" hook pre-tool-use --agent cursor)
[ -z "$out" ] || fail "a Cursor edit in scope should get no answer: $out"
printf '{"tool_name":"Write","tool_input":{"file_path":"%s/src/Billing/Invoice.php"},"workspace_roots":["%s"]}' "$work" "$work" \
    | "$kekkai" hook pre-tool-use --agent cursor | grep -q '"permission":"deny"' || fail "a Cursor edit outside the scope should be denied"

step "shell write outside the scope: caught at stop, once"
echo '<?php // sed' > src/Billing/Invoice.php
echo '<?php' > src/Catalog/Price.php
printf '{"cwd":"%s"}' "$work" | "$kekkai" hook stop | grep -q '"decision":"block"' || fail "the stop review should block"
out=$(printf '{"cwd":"%s","stop_hook_active":true}' "$work" | "$kekkai" hook stop)
[ -z "$out" ] || fail "a second stop should let the agent stop: $out"
printf '{"workspace_roots":["%s"],"status":"completed","loop_count":0}' "$work" | "$kekkai" hook stop --agent cursor \
    | grep -q '"followup_message"' || fail "the Cursor stop review should send the agent back"
out=$(printf '{"workspace_roots":["%s"],"status":"completed","loop_count":1}' "$work" | "$kekkai" hook stop --agent cursor)
[ -z "$out" ] || fail "a second Cursor stop should let the agent stop: $out"
if "$kekkai" check > "$logs/check.log"; then fail "kekkai check should fail"; fi
grep -q 'src/Billing/Invoice.php' "$logs/check.log" || fail "kekkai check should name the file"

step "widen with a reason: the review passes"
"$kekkai" scope widen src/Billing/Invoice.php -m "Invoices show the price" > /dev/null
"$kekkai" check > "$logs/check.log" || { cat "$logs/check.log"; fail "kekkai check should pass after widening"; }
grep -q 'Scope widened 1 time' "$logs/check.log" || fail "the widening should be reported"

step "doctor"
"$kekkai" doctor > "$logs/doctor.log" || { cat "$logs/doctor.log"; fail "doctor should pass"; }

printf 'smoke: %s passed\n' "$kekkai"
