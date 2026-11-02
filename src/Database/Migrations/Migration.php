<?php

declare(strict_types=1);

namespace Rocket\Database\Migrations;

/**
 * Class Migration
 *
 * Base class for database migrations.
 */
abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;
}

