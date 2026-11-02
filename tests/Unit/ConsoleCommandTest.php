<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Console\Application as ConsoleApp;
use Rocket\Console\Command;
use Rocket\Facades\Console;
use Rocket\Foundation\Application;

class ConsoleCommandTest extends TestCase
{
    protected Application $app;
    protected ConsoleApp $console;
    protected string $tempCommandsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
        $this->console = new ConsoleApp($this->app);

        $this->tempCommandsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_cmds_' . uniqid();
        mkdir($this->tempCommandsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempCommandsDir);
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
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_closure_command_registration_and_execution(): void
    {
        $executed = false;

        $this->console->command('test:closure', function () use (&$executed) {
            $executed = true;
            return 0;
        })->describe('A test closure command');

        $commands = $this->console->getCommands();
        $this->assertArrayHasKey('test:closure', $commands);
        $this->assertSame('A test closure command', $commands['test:closure']->getDescription());

        $exitCode = $this->console->call('test:closure');
        $this->assertSame(0, $exitCode);
        $this->assertTrue($executed);
    }

    public function test_custom_command_class_execution(): void
    {
        $customCommand = new class($this->app) extends Command {
            protected string $name = 'custom:test';
            protected string $description = 'Test custom command';
            public bool $handled = false;
            public array $passedArgs = [];

            public function handle(array $arguments, array $options): int
            {
                $this->handled = true;
                $this->passedArgs = $arguments;
                return 0;
            }
        };

        $this->console->add($customCommand);

        $code = $this->console->call('custom:test', ['arg1', 'arg2']);
        $this->assertSame(0, $code);
        $this->assertTrue($customCommand->handled);
        $this->assertSame(['arg1', 'arg2'], $customCommand->passedArgs);
    }

    public function test_make_command_scaffolding(): void
    {
        ob_start();
        $exitCode = $this->console->call('make:command', ['BackupDatabase'], [
            'command'     => 'db:backup',
            'description' => 'Backup the production database',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exitCode);

        $filePath = $this->app->appPath('Console/Commands/BackupDatabaseCommand.php');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('class BackupDatabaseCommand extends Command', $content);
        $this->assertStringContainsString("protected string \$name = 'db:backup';", $content);
        $this->assertStringContainsString("protected string \$description = 'Backup the production database';", $content);

        // Clean up created file
        unlink($filePath);
    }
}
