<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\NovaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Opscale\NovaAPI\Http\Controllers\APIController;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Tests\TestCase;
use Opscale\NovaAPI\ToolServiceProvider;
use Orion\OrionServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

#[CoversClass(APIController::class)]
final class APIEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    final public function api_returns_unauthorized_without_token(): void
    {
        $testResponse = $this->getJson('/api/users');

        $testResponse->assertStatus(401);
    }

    #[Test]
    final public function api_returns_forbidden_without_proper_abilities(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test Token', ['products:read']); // Only product abilities

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(403);
    }

    #[Test]
    final public function api_returns_data_with_proper_abilities(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create(); // Create additional users

        $newAccessToken = $user->createToken('Test Token', ['users:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ]);
    }

    #[Test]
    final public function api_can_create_resource_with_proper_abilities(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test Token', ['users:create']);

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', $userData);

        $testResponse->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    #[Test]
    final public function api_can_update_resource_with_proper_abilities(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create(['name' => 'Original Name']);

        $newAccessToken = $user->createToken('Test Token', ['users:update']);

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
    final public function api_can_delete_resource_with_proper_abilities(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $newAccessToken = $user->createToken('Test Token', ['users:delete']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->deleteJson('/api/users/'.$targetUser->id);

        $testResponse->assertStatus(200);
        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    #[Test]
    final public function api_works_with_products_resource(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(3)->create();

        $newAccessToken = $user->createToken('Test Token', ['products:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/products');

        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                    ],
                ],
            ]);
    }

    #[Test]
    final public function expired_token_returns_unauthorized(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Expired Token', ['users:read'], Carbon::now()->subHour());

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(401);
    }

    #[Test]
    final public function api_returns_proper_error_format(): void
    {
        $testResponse = $this->getJson('/api/nonexistent');

        $testResponse->assertStatus(404)
            ->assertJson([
                'message' => 'The route api/nonexistent could not be found.',
            ]);
    }

    #[Test]
    final public function api_handles_validation_errors(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test Token', ['users:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', [
            'name' => '', // Invalid: empty name
            'email' => 'invalid-email', // Invalid: bad email format
        ]);

        $testResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
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
