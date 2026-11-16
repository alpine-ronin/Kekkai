#!/bin/sh
# Installs Kekkai from its GitHub releases: the executable for this system (PHP included, nothing else needed), or
# kekkai.phar where there is none and PHP 8.3+ is installed. Checked against the release's SHA256SUMS and, when the
# GitHub CLI is logged in, its attestation: that the release workflow of alpine-ronin/kekkai built it.
#
#   curl -fsSL https://raw.githubusercontent.com/alpine-ronin/kekkai/main/install.sh | sh                  latest release
#   curl -fsSL https://raw.githubusercontent.com/alpine-ronin/kekkai/main/install.sh | sh -s -- 0.2.0     a given release
#   curl -fsSL https://raw.githubusercontent.com/alpine-ronin/kekkai/main/install.sh | sh -s -- edge      the last build of main
#
# KEKKAI_INSTALL_DIR picks the directory (default ~/.local/bin); KEKKAI_PHAR=1 installs the PHAR even where a binary
# exists. Running the script again updates Kekkai; so does `kekkai self-update`.
set -eu

releases="${KEKKAI_RELEASES_URL:-https://github.com/alpine-ronin/kekkai/releases}"
repository=alpine-ronin/kekkai
install_dir="${KEKKAI_INSTALL_DIR:-$HOME/.local/bin}"

fail() {
    printf 'kekkai: %s\n' "$1" >&2
    exit 1
}

usage() {
    printf 'Usage: install.sh [VERSION | edge]\n\nInstalls Kekkai VERSION (0.2.0 or v0.2.0), the last build of main (edge), or the latest release, into %s.\n' "$install_dir"
}

download() {
    if command -v curl > /dev/null 2>&1; then
        curl -fsSL -o "$2" "$1"
    elif command -v wget > /dev/null 2>&1; then
        wget -q -O "$2" "$1"
    else
        fail "curl or wget is needed to download Kekkai"
    fi
}

sha256() {
    if command -v sha256sum > /dev/null 2>&1; then
        sha256sum "$1" | cut -d ' ' -f 1
    elif command -v shasum > /dev/null 2>&1; then
        shasum -a 256 "$1" | cut -d ' ' -f 1
    else
        fail "sha256sum or shasum is needed to check the download"
    fi
}

# The release file for this machine: a binary, or kekkai.phar when there is none (or KEKKAI_PHAR=1).
asset() {
    if [ "${KEKKAI_PHAR:-}" != 1 ]; then
        case "$(uname -s)" in
            Linux) system=linux ;;
            Darwin) system=macos ;;
            *) system='' ;;
        esac
        case "$(uname -m)" in
            x86_64 | amd64) architecture=x86_64 ;;
            arm64 | aarch64) architecture=aarch64 ;;
            *) architecture='' ;;
        esac
        if [ "$system" = macos ] && [ "$architecture" = x86_64 ]; then
            system=''
        fi
        if [ -n "$system" ] && [ -n "$architecture" ]; then
            printf 'kekkai-%s-%s' "$system" "$architecture"
            return
        fi
    fi
    command -v php > /dev/null 2>&1 || fail "no Kekkai binary for $(uname -s) $(uname -m), and no PHP to run kekkai.phar: install PHP 8.3 or later"
    php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || fail "kekkai.phar needs PHP 8.3 or later, this is PHP $(php -r 'echo PHP_VERSION;')"
    printf 'kekkai.phar'
}

verify_attestation() {
    if ! command -v gh > /dev/null 2>&1 || ! gh auth status --hostname github.com > /dev/null 2>&1; then
        printf 'Not checked where %s was built: with the GitHub CLI installed and logged in, this script checks it.\n' "$file"
        return
    fi
    gh attestation verify "$1" --repo "$repository" > /dev/null 2>&1 \
        || fail "$file has no attestation that the release workflow of $repository built it: not installed"
    printf 'Checked: %s was built by the release workflow of %s.\n' "$file" "$repository"
}

case "${1:-}" in
    -h | --help)
        usage
        exit 0
        ;;
    '' | latest) base="$releases/latest/download" ;;
    edge | dev-main | main) base="$releases/download/edge" ;;
    *) base="$releases/download/v${1#v}" ;;
esac

file="$(asset)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

printf 'Downloading %s from %s\n' "$file" "$base"
download "$base/$file" "$work/$file" || fail "could not download $base/$file: is there such a release? See https://github.com/$repository/releases"
download "$base/SHA256SUMS" "$work/SHA256SUMS" || fail "could not download $base/SHA256SUMS"

expected="$(awk -v name="$file" '$2 == name || $2 == "*" name { print $1 }' "$work/SHA256SUMS")"
[ -n "$expected" ] || fail "SHA256SUMS lists no $file"
[ "$(sha256 "$work/$file")" = "$expected" ] || fail "$file does not match its checksum: not installed"
verify_attestation "$work/$file"

mkdir -p "$install_dir"
chmod +x "$work/$file"
mv "$work/$file" "$install_dir/kekkai"
if [ "$(uname -s)" = Darwin ]; then
    xattr -d com.apple.quarantine "$install_dir/kekkai" 2> /dev/null || true
fi

printf 'Installed %s in %s\n' "$("$install_dir/kekkai" --version 2> /dev/null || printf 'kekkai')" "$install_dir/kekkai"
case ":$PATH:" in
    *":$install_dir:"*) ;;
    *) printf 'Add %s to your PATH to run kekkai by name: export PATH="%s:%sPATH"\n' "$install_dir" "$install_dir" '$' ;;
esac
printf 'Then, in your project: kekkai init && kekkai install claude --write && kekkai doctor\n'
