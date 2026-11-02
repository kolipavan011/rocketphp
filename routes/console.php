<?php

declare(strict_types=1);

use Rocket\Facades\Console;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure-based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Console::command('inspire', function () {
    $quotes = [
        "Simplicity is prerequisite for reliability. — Edsger W. Dijkstra",
        "Make it work, make it right, make it fast. — Kent Beck",
        "Premature optimization is the root of all evil. — Donald Knuth",
        "Speed and security are not features; they are foundational requirements. — Rocketphp",
    ];

    $this->info($quotes[array_rand($quotes)]);
})->describe('Display an inspiring architectural quote');

