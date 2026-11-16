# Install

Kekkai needs PHP 8.3 or later and git. The project it guards may be in any language.

Kekkai is not on Packagist yet: until the first release, declare the repository in `composer.json` and require
`dev-main`:

```json
"repositories": [{"type": "vcs", "url": "https://github.com/alpine-ronin/kekkai"}]
```

## In a PHP project (recommended)

```bash
composer require --dev alpine-ronin/kekkai:dev-main
vendor/bin/kekkai init
vendor/bin/kekkai install claude --write
```

The hooks then call `"$CLAUDE_PROJECT_DIR"/vendor/bin/kekkai`: every developer of the team uses the version locked in
`composer.lock`.

## Globally

```bash
composer global config repositories.kekkai vcs https://github.com/alpine-ronin/kekkai
composer global require alpine-ronin/kekkai:dev-main
kekkai init
kekkai install claude --write
```

Make sure Composer's global `vendor/bin` is on your `PATH` (`composer global config bin-dir --absolute`); the hooks
then call `kekkai`.

## From source

```bash
git clone https://github.com/alpine-ronin/kekkai
cd kekkai && composer install
bin/kekkai --version
```

## Check the setup

```bash
kekkai scope declare src/ -m "Try Kekkai"
echo '{"tool_name":"Write","tool_input":{"file_path":"'$PWD'/README.md"}}' | kekkai hook pre-tool-use
kekkai scope clear
```

The second command prints a `deny`: `README.md` is outside the scope `src/`.

## Uninstall

Remove the `kekkai` entries from `.claude/settings.json` and `.mcp.json`, then `kekkai.yaml` and `.kekkai/`.
