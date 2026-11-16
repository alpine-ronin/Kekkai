<?php

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const BUILD = ROOT . '/build';
const STAGING = BUILD . '/phar-src';

/**
 * @param string $message
 */
function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/**
 * @param string $command
 */
function run(string $command): void
{
    passthru($command, $code);
    if ($code !== 0) {
        fail('Command failed: ' . $command);
    }
}

/**
 * @param string $from
 * @param string $to
 */
function copyTree(string $from, string $to): void
{
    @mkdir($to, 0o775, true);
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? @mkdir($target, 0o775, true) : copy($item->getPathname(), $target);
    }
}

/**
 * @param string $directory
 */
function removeTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item instanceof SplFileInfo) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
    rmdir($directory);
}

if (ini_get('phar.readonly') === '1') {
    fail('Run with: php -d phar.readonly=0 tools/build-phar.php');
}

$output = $argv[1] ?? BUILD . '/kekkai.phar';

removeTree(STAGING);
copyTree(ROOT . '/src', STAGING . '/src');
foreach (['composer.json', 'composer.lock', 'LICENSE'] as $file) {
    if (is_file(ROOT . '/' . $file)) {
        copy(ROOT . '/' . $file, STAGING . '/' . $file);
    }
}

$vendor = getenv('KEKKAI_PHAR_VENDOR');
if (is_string($vendor) && $vendor !== '') {
    copyTree($vendor, STAGING . '/vendor');
    run(sprintf('composer dump-autoload --working-dir=%s --no-dev --classmap-authoritative --no-scripts --quiet', escapeshellarg(STAGING)));
} else {
    run(sprintf('composer install --working-dir=%s --no-dev --classmap-authoritative --no-scripts --no-plugins --prefer-dist --no-interaction --quiet', escapeshellarg(STAGING)));
}

$version = getenv('KEKKAI_VERSION') ?: trim((string) shell_exec('git -C ' . escapeshellarg(ROOT) . ' describe --tags --always 2>/dev/null')) ?: 'dev';
$version = ltrim($version, 'v');
$versionFile = STAGING . '/src/Version.php';
file_put_contents($versionFile, str_replace("'@kekkai_version@'", var_export($version, true), (string) file_get_contents($versionFile)));

@mkdir(STAGING . '/bin', 0o775, true);
$entry = (string) file_get_contents(ROOT . '/bin/kekkai');
file_put_contents(STAGING . '/bin/kekkai.php', preg_replace('/^#!.*\n/', '', $entry));

@mkdir(dirname($output), 0o775, true);
@unlink($output);
$phar = new Phar($output, 0, 'kekkai.phar');
$phar->startBuffering();
$phar->buildFromDirectory(STAGING);
$phar->setStub("#!/usr/bin/env php\n<?php\nPhar::mapPhar('kekkai.phar');\nrequire 'phar://kekkai.phar/bin/kekkai.php';\n__HALT_COMPILER();\n");
$phar->stopBuffering();
chmod($output, 0o755);

removeTree(STAGING);
printf("%s %s (%.1f MB)\n", realpath($output), $version, filesize($output) / 1048576);
