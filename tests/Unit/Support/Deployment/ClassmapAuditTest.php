<?php

namespace Tests\Unit\Support\Deployment;

use App\Support\Deployment\ClassmapAudit;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClassmapAuditTest extends TestCase
{
    #[Test]
    public function it_reports_existing_classmap_files_loaded_from_a_nested_worktree(): void
    {
        $filesystem = new Filesystem;
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'classmap-audit-'.bin2hex(random_bytes(6));
        $composer = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer';
        $localFile = $root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'LocalClass.php';
        $foreignFile = $root.DIRECTORY_SEPARATOR.'.worktrees'.DIRECTORY_SEPARATOR.'other-branch'
            .DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'ForeignClass.php';

        $filesystem->ensureDirectoryExists(dirname($localFile));
        $filesystem->ensureDirectoryExists(dirname($foreignFile));
        $filesystem->ensureDirectoryExists($composer);
        $filesystem->put($localFile, "<?php\n");
        $filesystem->put($foreignFile, "<?php\n");

        $autoload = $composer.DIRECTORY_SEPARATOR.'autoload_static.php';
        $filesystem->put($autoload, implode("\n", [
            '<?php',
            'class FixtureComposerStaticInit {',
            '    public static $classMap = array (',
            "        'App\\LocalClass' => ".var_export($localFile, true).',',
            "        'App\\ForeignClass' => ".var_export($foreignFile, true).',',
            '    );',
            '}',
        ]));

        try {
            $foreign = (new ClassmapAudit)->foreignInStatic($autoload, $root);

            $this->assertCount(1, $foreign);
            $this->assertSame($foreignFile, $foreign[0]['path']);
        } finally {
            $filesystem->deleteDirectory($root);
        }
    }
}
