<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\Pdo\PdoUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PdoUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_inserts_a_user_with_raw_pdo(): void
    {
        $user = app(PdoUserRepository::class)->create([
            'name' => 'Pdo User',
            'email' => 'pdo@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->exists);
        $this->assertSame('pdo@example.com', $user->email);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'pdo@example.com']);
    }

    public function test_find_by_email_and_id_return_the_user(): void
    {
        $created = User::factory()->create(['email' => 'find@example.com']);
        $repository = app(PdoUserRepository::class);

        $this->assertSame($created->id, $repository->findByEmail('find@example.com')?->id);
        $this->assertSame('find@example.com', $repository->findById($created->id)?->email);
    }

    public function test_find_returns_null_when_missing(): void
    {
        $repository = app(PdoUserRepository::class);

        $this->assertNull($repository->findByEmail('nobody@example.com'));
        $this->assertNull($repository->findById(999999));
    }
}
