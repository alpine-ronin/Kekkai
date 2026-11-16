<?php

declare(strict_types=1);

namespace Kekkai\Tests\Command;

use Kekkai\Command\SelfUpdateCommand;
use Kekkai\Tests\Support\TemporaryProject;
use Kekkai\Update\Installation;
use Kekkai\Update\Releases;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SelfUpdateCommandTest extends TestCase
{
    private TemporaryProject $directory;

    private string $installed;

    protected function setUp(): void
    {
        $this->directory = new TemporaryProject(git: false);
        $this->release('latest/download', '9.9.9', 'new binary');
        $this->release('download/v1.2.3', '1.2.3', 'older binary');
        $this->release('download/edge', 'dev-main', 'edge binary');
        $this->directory->write('bin/kekkai', 'old binary');
        $this->installed = $this->directory->root . '/bin/kekkai';
    }

    public function testUpdatesToTheLatestRelease(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute(['--no-attestation' => true]));
        self::assertStringContainsString('-> 9.9.9: kekkai-linux-x86_64 checked against SHA256SUMS (attestation check skipped)', $tester->getDisplay());
        self::assertSame('new binary', file_get_contents($this->installed));
        self::assertTrue(is_executable($this->installed));
        self::assertSame([], glob($this->directory->root . '/bin/.kekkai-update-*'));
    }

    public function testAGivenVersionAndEdge(): void
    {
        $this->tester()->execute(['version' => 'v1.2.3', '--no-attestation' => true]);
        self::assertSame('older binary', file_get_contents($this->installed));

        $tester = $this->tester();
        $tester->execute(['version' => 'dev-main', '--no-attestation' => true]);
        self::assertSame('edge binary', file_get_contents($this->installed));
        self::assertStringContainsString('-> dev-main', $tester->getDisplay());
    }

    public function testRefusesADownloadThatDoesNotMatchItsChecksum(): void
    {
        $this->directory->write('releases/latest/download/kekkai-linux-x86_64', 'tampered');
        $tester = $this->tester();

        self::assertSame(1, $tester->execute(['--no-attestation' => true]));
        self::assertStringContainsString('does not match its checksum: not installed', $tester->getDisplay());
        self::assertSame('old binary', file_get_contents($this->installed));
        self::assertSame([], glob($this->directory->root . '/bin/.kekkai-update-*'));
    }

    public function testUnknownRelease(): void
    {
        $tester = $this->tester();

        self::assertSame(1, $tester->execute(['version' => '4.0.0', '--no-attestation' => true]));
        self::assertStringContainsString('download/v4.0.0/', $tester->getDisplay());
        self::assertSame('old binary', file_get_contents($this->installed));
    }

    public function testCheck(): void
    {
        $tester = $this->tester();

        self::assertSame(1, $tester->execute(['--check' => true]));
        self::assertStringContainsString('Kekkai 9.9.9 is available', $tester->getDisplay());
        self::assertSame('old binary', file_get_contents($this->installed));
    }

    public function testInstalledWithComposer(): void
    {
        $tester = new CommandTester(new SelfUpdateCommand($this->releases(), new Installation(Installation::COMPOSER, null, 'kekkai.phar'), replace: false));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('composer update alpine-ronin/kekkai', $tester->getDisplay());
    }

    public function testReleasesReadsChecksumsWithBinaryMarkers(): void
    {
        $this->directory->write('releases/latest/download/SHA256SUMS', str_repeat('a', 64) . ' *kekkai.phar' . "\n");

        self::assertSame(str_repeat('a', 64), $this->releases()->checksum('latest', 'kekkai.phar'));
        self::assertSame('https://example.com/r/download/v1.0.0/kekkai.phar', (new Releases('https://example.com/r/'))->url('v1.0.0', 'kekkai.phar'));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new SelfUpdateCommand($this->releases(), new Installation(Installation::BINARY, $this->installed, 'kekkai-linux-x86_64'), replace: false));
    }

    private function releases(): Releases
    {
        return new Releases('file://' . $this->directory->root . '/releases');
    }

    /**
     * @param string $path
     * @param string $version
     * @param string $content
     */
    private function release(string $path, string $version, string $content): void
    {
        $this->directory->write(sprintf('releases/%s/VERSION', $path), $version . "\n");
        $this->directory->write(sprintf('releases/%s/kekkai-linux-x86_64', $path), $content);
        $this->directory->write(sprintf('releases/%s/SHA256SUMS', $path), hash('sha256', $content) . "  kekkai-linux-x86_64\n");
    }
}
