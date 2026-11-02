<?php

declare(strict_types=1);

namespace App\Models;

use Rocket\Database\ORM\Model;

class User extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];
}