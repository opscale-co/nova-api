<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\NovaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Policies\APIPolicy;
use Opscale\NovaAPI\Tests\TestCase;
use Opscale\NovaAPI\ToolServiceProvider;
use Orion\OrionServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

#[CoversClass(APIPolicy::class)]
final class TokenAbilitiesTest extends TestCase
{
    use DatabaseTransactions;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    final public function token_with_read_ability_can_view_resources(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        $newAccessToken = $user->createToken('Read Token', ['users:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(200)
            ->assertJsonCount(4, 'data'); // Original user + 3 created
    }

    #[Test]
    final public function token_with_create_ability_can_create_resources(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Create Token', ['users:create']);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', $userData);

        $testResponse->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    #[Test]
    final public function token_with_update_ability_can_update_resources(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create(['name' => 'Original Name']);

        $newAccessToken = $user->createToken('Update Token', ['users:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->putJson('/api/users/'.$targetUser->id, [
            'name' => 'Updated Name',
            'email' => 'updated'.$targetUser->email,
            'password' => 'newpassword123',
        ]);

        $testResponse->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    final public function token_with_delete_ability_can_delete_resources(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $newAccessToken = $user->createToken('Delete Token', ['users:delete']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->deleteJson('/api/users/'.$targetUser->id);

        $testResponse->assertStatus(200);
        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    #[Test]
    final public function token_without_read_ability_cannot_view_resources(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('No Read Token', ['users:create', 'users:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(403);
    }

    #[Test]
    final public function token_without_create_ability_cannot_create_resources(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('No Create Token', ['users:read', 'users:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $testResponse->assertStatus(403);
    }

    #[Test]
    final public function token_without_update_ability_cannot_update_resources(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $newAccessToken = $user->createToken('No Update Token', ['users:read', 'users:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->putJson('/api/users/'.$targetUser->id, [
            'name' => 'Updated Name',
            'email' => 'updated'.$targetUser->email,
            'password' => 'newpassword123',
        ]);

        $testResponse->assertStatus(403);
    }

    #[Test]
    final public function token_without_delete_ability_cannot_delete_resources(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $newAccessToken = $user->createToken('No Delete Token', ['users:read', 'users:create', 'users:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->deleteJson('/api/users/'.$targetUser->id);

        $testResponse->assertStatus(403);
    }

    #[Test]
    final public function token_with_multiple_resource_abilities(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(2)->create();

        $newAccessToken = $user->createToken('Multi Resource Token', [
            'users:read',
            'products:read',
            'products:create',
        ]);

        // Can read users
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');
        $response->assertStatus(200);

        // Can read products
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/products');
        $response->assertStatus(200);

        // Can create products
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/products', [
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
        ]);
        $response->assertStatus(201);

        // Cannot create users (no users:create ability)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(403);
    }

    #[Test]
    final public function token_abilities_are_resource_specific(): void
    {
        $user = User::factory()->create();
        Product::factory()->create();

        // Token only has user abilities, not product abilities
        $newAccessToken = $user->createToken('User Only Token', ['users:read', 'users:create']);

        // Can access users
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');
        $response->assertStatus(200);

        // Cannot access products
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/products');
        $response->assertStatus(403);
    }

    #[Test]
    final public function expired_token_cannot_access_any_resources(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Expired Token', ['users:read', 'products:read'], Carbon::now()->subHour());

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(401);
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            ToolServiceProvider::class,
            SanctumServiceProvider::class,
            NovaServiceProvider::class,
            \Workbench\App\Providers\NovaServiceProvider::class,
            OrionServiceProvider::class,
        ]);
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        $app['config']->set('nova-api.resources', [
            \Workbench\App\Nova\User::class,
            \Workbench\App\Nova\Product::class,
            AccessToken::class,
        ]);
    }
}
