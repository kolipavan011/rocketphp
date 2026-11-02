<?php

declare(strict_types=1);

namespace Rocket\Database\ORM;

use Exception;

class ModelNotFoundException extends Exception
{
    public function getStatusCode(): int
    {
        return 404;
    }
}

