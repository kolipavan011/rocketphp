<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Facades\Vite as ViteFacade;
use Rocket\Foundation\Application;
use Rocket\Foundation\Vite;
use Rocket\View\Compiler;

class ViteTest extends TestCase
{
    protected Application $app;
    protected Vite $vite;
    protected string $tempPublic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
        $this->vite = new Vite($this->app);
        $this->app->instance('vite', $this->vite);

        $this->tempPublic = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_vite_test_' . uniqid();
        mkdir($this->tempPublic . '/build', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempPublic);
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

    public function test_hot_mode_tags_generation(): void
    {
        $hotFile = $this->tempPublic . '/hot';
        file_put_contents($hotFile, 'http://localhost:5173');

        $this->vite->useHotFile($hotFile);

        $this->assertTrue($this->vite->isRunningHot());
        $this->assertSame('http://localhost:5173', $this->vite->getHotUrl());

        $html = ($this->vite)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/@vite/client"></script>', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/resources/js/app.js"></script>', $html);
    }

    public function test_manifest_production_tags_generation(): void
    {
        $hotFile = $this->tempPublic . '/non_existent_hot';
        $this->vite->useHotFile($hotFile);

        $manifestData = [
            'resources/css/app.css' => [
                'file'    => 'assets/app-12345.css',
                'src'     => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file'    => 'assets/app-67890.js',
                'name'    => 'app',
                'src'     => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ];

        file_put_contents($this->tempPublic . '/build/manifest.json', json_encode($manifestData));

        // Point Vite to custom public test directory
        $this->vite->usePublicPath($this->tempPublic);

        $this->assertFalse($this->vite->isRunningHot());

        $html = ($this->vite)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/app-12345.css">', $html);
        $this->assertStringContainsString('<script type="module" src="/build/assets/app-67890.js"></script>', $html);
    }

    public function test_compiler_compiles_vite_directive(): void
    {
        $compiler = new Compiler(sys_get_temp_dir());
        $compiled = $compiler->compileString("@vite(['resources/css/app.css', 'resources/js/app.js'])");

        $this->assertStringContainsString("<?= vite(['resources/css/app.css', 'resources/js/app.js']); ?>", $compiled);
    }
}
