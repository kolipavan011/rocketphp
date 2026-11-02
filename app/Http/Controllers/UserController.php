<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Rocket\Routing\Controller;
use Rocket\Http\Request;
use Rocket\Http\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->json(['message' => 'Hello from UserController']);
    }
}