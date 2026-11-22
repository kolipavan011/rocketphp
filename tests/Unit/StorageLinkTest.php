<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Console\Application as ConsoleApp;
use Rocket\Foundation\Application as CoreApp;

class StorageLinkTest extends TestCase
{
    protected CoreApp $app;
    protected ConsoleApp $console;
    protected string $tempBaseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempBaseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_storage_test_' . uniqid();
        mkdir($this->tempBaseDir, 0755, true);

        $this->app = new CoreApp($this->tempBaseDir);
        $this->console = new ConsoleApp($this->app);
    }

    protected function tearDown(): void
    {
        // Unlink if exists
        $link = $this->app->publicPath('storage');
        if (PHP_OS_FAMILY === 'Windows') {
            @rmdir($link);
        } else {
            @unlink($link);
        }

        $this->removeDir($this->tempBaseDir);
        parent::tearDown();
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_storage_link_creates_link_and_allows_public_access(): void
    {
        ob_start();
        $status = $this->console->call('storage:link');
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('The [public/storage] link has been connected to [storage/public].', $output);

        $target = $this->app->storagePath('public');
        $link = $this->app->publicPath('storage');

        $this->assertTrue(is_dir($target));
        $this->assertTrue(file_exists($link));

        // Test reading through the link
        $testFileName = 'document_' . uniqid() . '.txt';
        $testContent = 'Rocketphp storage content ' . rand(1000, 9999);

        file_put_contents($target . DIRECTORY_SEPARATOR . $testFileName, $testContent);

        $this->assertFileExists($link . DIRECTORY_SEPARATOR . $testFileName);
        $this->assertSame($testContent, file_get_contents($link . DIRECTORY_SEPARATOR . $testFileName));
    }

    public function test_storage_link_fails_if_already_exists_without_force(): void
    {
        // First creation
        ob_start();
        $this->console->call('storage:link');
        ob_end_clean();

        // Second creation without force
        ob_start();
        $status = $this->console->call('storage:link');
        $output = ob_get_clean();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('The [public/storage] link already exists.', $output);
    }

    public function test_storage_link_succeeds_with_force_flag(): void
    {
        // First creation
        ob_start();
        $this->console->call('storage:link');
        ob_end_clean();

        // Second creation with --force
        ob_start();
        $status = $this->console->call('storage:link', ['--force' => true]);
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('The [public/storage] link has been connected to [storage/public].', $output);
    }

    public function test_storage_unlink_removes_link_without_deleting_target_contents(): void
    {
        // 1. Create link
        ob_start();
        $this->console->call('storage:link');
        ob_end_clean();

        $target = $this->app->storagePath('public');
        $link = $this->app->publicPath('storage');

        $sampleFile = $target . DIRECTORY_SEPARATOR . 'persist.txt';
        file_put_contents($sampleFile, 'Permanent Data');

        // 2. Unlink
        ob_start();
        $status = $this->console->call('storage:unlink');
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('The [public/storage] link has been deleted.', $output);

        // Link should no longer exist
        $this->assertFalse(file_exists($link));

        // Original target file must be preserved
        $this->assertFileExists($sampleFile);
        $this->assertSame('Permanent Data', file_get_contents($sampleFile));
    }

    public function test_storage_path_and_public_path_helpers(): void
    {
        $this->assertSame(
            $this->app->storagePath('public/avatar.png'),
            storage_path('public/avatar.png')
        );

        $this->assertSame(
            $this->app->publicPath('storage/avatar.png'),
            public_path('storage/avatar.png')
        );

        $this->assertSame(
            '/storage/avatar.png',
            asset('storage/avatar.png')
        );
    }
}
