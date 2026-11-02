<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\Database\Connection;
use Rocket\Database\DatabaseManager;
use Rocket\Database\ORM\Model;
use Rocket\Database\ORM\SoftDeletes;
use Rocket\Database\Schema\Blueprint;
use Rocket\Database\Schema\Schema;
use Rocket\Facades\DB;
use Rocket\Foundation\Config;

class User extends Model
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'status'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}

class Post extends Model
{
    use SoftDeletes;

    protected ?string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

class DatabaseTest extends TestCase
{
    protected DatabaseManager $db;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        Container::setInstance($container);

        $config = new Config('', [
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver'   => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);

        $this->db = new DatabaseManager($config);
        $this->connection = $this->db->connection('sqlite');

        $container->instance('config', $config);
        $container->instance('db', $this->db);

        $this->setUpSchema();
    }

    protected function setUpSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_query_builder_crud_and_aggregates(): void
    {
        // 1. Insert
        $id1 = DB::table('users')->insertGetId([
            'name'  => 'Alice',
            'email' => 'alice@rocket.test',
            'status'=> 'active',
        ]);
        $id2 = DB::table('users')->insertGetId([
            'name'  => 'Bob',
            'email' => 'bob@rocket.test',
            'status'=> 'inactive',
        ]);

        $this->assertSame(1, (int) $id1);
        $this->assertSame(2, (int) $id2);

        // 2. Select & Where
        $user = DB::table('users')->where('email', 'alice@rocket.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Alice', $user['name']);

        // 3. Count & Aggregates
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame(1, DB::table('users')->where('status', 'active')->count());

        // 4. Update
        $affected = DB::table('users')->where('id', $id2)->update(['status' => 'active']);
        $this->assertSame(1, $affected);

        $updated = DB::table('users')->find($id2);
        $this->assertSame('active', $updated['status']);

        // 5. Delete
        DB::table('users')->where('id', $id1)->delete();
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_transactions(): void
    {
        try {
            DB::transaction(function () {
                DB::table('users')->insert(['name' => 'John', 'email' => 'john@test.com']);
                throw new \Exception('Rollback please');
            });
        } catch (\Exception $e) {
            // caught
        }

        $this->assertSame(0, DB::table('users')->where('email', 'john@test.com')->count());
    }

    public function test_active_record_model_crud(): void
    {
        $user = User::create([
            'name'  => 'Charlie',
            'email' => 'charlie@test.com',
        ]);

        $this->assertTrue($user->exists());
        $this->assertNotNull($user->id);
        $this->assertNotNull($user->created_at);

        $user->name = 'Charles';
        $user->save();

        $fresh = User::find($user->id);
        $this->assertSame('Charles', $fresh->name);

        $user->delete();
        $this->assertNull(User::find($user->id));
    }

    public function test_relationships_and_eager_loading_prevents_n_plus_one(): void
    {
        $user = User::create(['name' => 'Author', 'email' => 'author@test.com']);

        Post::create(['user_id' => $user->id, 'title' => 'First Post', 'content' => 'Content 1']);
        Post::create(['user_id' => $user->id, 'title' => 'Second Post', 'content' => 'Content 2']);

        // Test dynamic relationship access
        $posts = $user->posts;
        $this->assertCount(2, $posts);
        $this->assertSame('First Post', $posts[0]->title);
        $this->assertSame('Author', $posts[0]->user->name);

        // Test Eager Loading with batch WHERE IN
        $usersWithPosts = User::query()->with('posts')->where('id', $user->id)->get();
        $this->assertCount(1, $usersWithPosts);

        $loadedPosts = $usersWithPosts[0]->posts;
        $this->assertCount(2, $loadedPosts);
    }

    public function test_soft_deletes(): void
    {
        $user = User::create(['name' => 'Poster', 'email' => 'poster@test.com']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Soft Post']);

        $postId = $post->id;
        $this->assertNull($post->deleted_at);

        // Soft delete
        $post->delete();
        $this->assertNotNull($post->deleted_at);
        $this->assertTrue($post->trashed());

        // Regular query filters it out
        $this->assertNull(Post::find($postId));

        // Restore
        $post->restore();
        $this->assertFalse($post->trashed());
        $this->assertNotNull(Post::find($postId));
    }

    public function test_paginator(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            User::create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
        }

        $page1 = User::query()->paginate(10, 1);
        $this->assertCount(10, $page1->items());
        $this->assertSame(25, $page1->total());
        $this->assertSame(3, $page1->lastPage());
        $this->assertTrue($page1->hasMorePages());

        $page3 = User::query()->paginate(10, 3);
        $this->assertCount(5, $page3->items());
        $this->assertFalse($page3->hasMorePages());
    }
}

