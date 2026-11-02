<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new database migration file';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Migration name is required. Usage: php rocket make:migration <name>');
            return 1;
        }

        $directory = $this->app->databasePath('migrations');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;

        $table = 'example';
        if (preg_match('/create_(.*)_table/', $name, $matches)) {
            $table = $matches[1];
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

use Rocket\Database\Migrations\Migration;
use Rocket\Database\Schema\Blueprint;
use Rocket\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        file_put_contents($filePath, $template);
        $this->success("Migration [{$filename}] created successfully at database/migrations/{$filename}");

        return 0;
    }
}

