<?php

declare(strict_types=1);

namespace Rocket\Routing\Exceptions;

use Exception;

class RouteNotFoundException extends Exception
{
    public function getStatusCode(): int
    {
        return 404;
    }
}

