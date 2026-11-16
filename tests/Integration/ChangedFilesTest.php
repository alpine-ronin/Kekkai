<?php

declare(strict_types=1);

namespace Kekkai\Tests\Integration;

use Kekkai\Change\ChangedFiles;
use Kekkai\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class ChangedFilesTest extends TestCase
{
    public function testEveryKindOfChange(): void
    {
        $project = (new TemporaryProject())
            ->write('src/Modified.php')
            ->write('src/Deleted.php')
            ->write('src/Renamed.php', "<?php\nfinal class Renamed {}\n")
            ->write('.gitignore', "var/\n")
            ->commit();

        $project
            ->write('src/Modified.php', "<?php\n// changed\n")
            ->delete('src/Deleted.php')
            ->write('src/Staged.php')
            ->write('src/Untracked.php')
            ->write('var/cache.php');
        $project->git('add', 'src/Staged.php');
        $project->git('mv', 'src/Renamed.php', 'src/Moved.php');

        self::assertSame(
            ['src/Deleted.php', 'src/Modified.php', 'src/Moved.php', 'src/Renamed.php', 'src/Staged.php', 'src/Untracked.php'],
            (new ChangedFiles($project->root))->since(),
        );
    }

    public function testSinceAnOlderRevision(): void
    {
        $project = (new TemporaryProject())->write('a.php')->commit('first');
        $first = trim($project->git('rev-parse', 'HEAD'));
        $project->write('b.php')->commit('second')->write('c.php');

        self::assertSame(['c.php'], (new ChangedFiles($project->root))->since());
        self::assertSame(['b.php', 'c.php'], (new ChangedFiles($project->root))->since($first));
    }

    public function testRepositoryWithoutCommits(): void
    {
        $project = (new TemporaryProject())->write('staged.php')->write('untracked.php');
        $project->git('add', 'staged.php');

        self::assertSame(['staged.php', 'untracked.php'], (new ChangedFiles($project->root))->since());
    }

    public function testFileNamesWithSpaces(): void
    {
        $project = (new TemporaryProject())->commit()->write('docs/a file.md');

        self::assertSame(['docs/a file.md'], (new ChangedFiles($project->root))->since());
    }

    public function testOutsideGit(): void
    {
        $project = new TemporaryProject(git: false);

        $this->expectExceptionMessage('is not a git repository');

        (new ChangedFiles($project->root))->since();
    }

    public function testUnknownRevision(): void
    {
        $project = (new TemporaryProject())->write('a.php')->commit();

        $this->expectExceptionMessage('Unknown git revision: nope.');

        (new ChangedFiles($project->root))->since('nope');
    }
}
