# Install

Kekkai guards projects in any language; it needs git in the project. Three ways to install it:

| | Needs | Updated with |
|---|---|---|
| [The binary](#the-binary-or-the-phar-with-the-install-script) | nothing (PHP is inside) | `kekkai self-update` |
| [The PHAR](#the-binary-or-the-phar-with-the-install-script) | PHP 8.3+ | `kekkai self-update` |
| [Composer](#with-composer) | PHP 8.3+, a PHP project | `composer update alpine-ronin/kekkai` |

## The binary, or the PHAR, with the install script

```bash
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/kekkai/main/install.sh | sh
```

The script:

1. picks the file for this machine: `kekkai-linux-x86_64`, `kekkai-linux-aarch64` or `kekkai-macos-aarch64`; on any
   other system (an Intel Mac, a BSD) `kekkai.phar`, if PHP 8.3 or later is installed;
2. downloads it and the release's `SHA256SUMS` from https://github.com/alpine-ronin/kekkai/releases, and refuses a
   file that does not match;
3. with the GitHub CLI installed and logged in, checks its attestation (`gh attestation verify`), and refuses a file
   without one;
4. installs it as `~/.local/bin/kekkai`, and says how to put that directory on your `PATH` if it is not.

| Variable or argument | Effect |
|---|---|
| `sh -s -- 0.2.0` (or `v0.2.0`) | That release instead of the latest |
| `sh -s -- edge` | The last build of `main` (the `edge` pre-release, rebuilt on every push) |
| `KEKKAI_PHAR=1` | The PHAR, even where a binary exists |
| `KEKKAI_INSTALL_DIR=/usr/local/bin` | Another directory |
| `KEKKAI_RELEASES_URL` | Another place to download from (a mirror, a test) |

Then, in the project: `kekkai init && kekkai install claude --write && kekkai doctor`.

### By hand

Download the file for your machine and `SHA256SUMS` from the [releases](https://github.com/alpine-ronin/kekkai/releases),
then:

```bash
sha256sum --ignore-missing -c SHA256SUMS
gh attestation verify kekkai-linux-x86_64 --repo alpine-ronin/kekkai
chmod +x kekkai-linux-x86_64 && mv kekkai-linux-x86_64 ~/.local/bin/kekkai
```

The macOS binary is not signed by Apple: downloaded with a browser, Gatekeeper refuses its first run. Clear the
download mark with `xattr -d com.apple.quarantine kekkai-macos-aarch64` (the install script does it; curl sets no
such mark anyway).

### Updating

```bash
kekkai self-update              # the latest release
kekkai self-update 0.2.0        # a given one, newer or older
kekkai self-update edge         # the last build of main
kekkai self-update --check      # exit code 1 when a newer release exists
```

The same checks as the install script, then the new file takes the place of the running one. An MCP server already
running keeps the old version: restart your MCP client (in Claude Code, `/mcp`, then reconnect `kekkai`). The hooks
use the new version from their next call.

## With Composer

Kekkai is not on Packagist yet. Until then, declare the repository in the project's `composer.json`:

```json
"repositories": [{"type": "vcs", "url": "https://github.com/alpine-ronin/kekkai"}]
```

```bash
composer require --dev alpine-ronin/kekkai:^0.1
vendor/bin/kekkai init
vendor/bin/kekkai install claude --write
```

The hooks then call `"$CLAUDE_PROJECT_DIR"/vendor/bin/kekkai`: every developer of the team runs the version locked in
`composer.lock`. Kekkai requires `symfony/console` and `symfony/yaml` 7; with Symfony 6 in the project, use the binary
or the PHAR instead.

Globally:

```bash
composer global config repositories.kekkai vcs https://github.com/alpine-ronin/kekkai
composer global require alpine-ronin/kekkai:^0.1
```

## From source

```bash
git clone https://github.com/alpine-ronin/kekkai && cd kekkai
composer install
bin/kekkai --version
composer phar        # build/kekkai.phar
```

## Check the setup

```bash
kekkai doctor
```

Or by hand, in any git repository:

```bash
kekkai scope declare src/ -m "Try Kekkai"
echo '{"tool_name":"Write","tool_input":{"file_path":"'"$PWD"'/README.md"}}' | kekkai hook pre-tool-use
kekkai scope clear
```

The second command prints a `deny`: `README.md` is outside the scope `src/`.

## Uninstall

Remove the `kekkai` entries from `.claude/settings.json` and `.mcp.json`, then `kekkai.yaml` and `.kekkai/` from the
project, and `~/.local/bin/kekkai`.
