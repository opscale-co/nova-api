<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

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
final class APICrudTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function can_show_single_user_resource(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['name' => 'Target User']);
        $newAccessToken = $user->createToken('Test', ['users:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users/'.$target->id);

        $testResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Target User');
    }

    #[Test]
    final public function show_returns_not_found_for_nonexistent_resource(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['users:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users/99999');

        $testResponse->assertStatus(404);
    }

    #[Test]
    final public function can_create_product_resource(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'description' => 'A test product',
            'price' => 29.99,
            'stock' => 50,
        ]);

        $testResponse->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'price' => 29.99,
        ]);
    }

    #[Test]
    final public function can_update_product_resource(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Original']);
        $newAccessToken = $user->createToken('Test', ['products:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->putJson('/api/products/'.$product->id, [
            'name' => 'Updated Product',
            'price' => 19.99,
            'stock' => 10,
        ]);

        $testResponse->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
        ]);
    }

    #[Test]
    final public function can_delete_product_resource(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:delete']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->deleteJson('/api/products/'.$product->id);

        $testResponse->assertStatus(200);
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    #[Test]
    final public function update_returns_not_found_for_nonexistent_product(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:update']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->putJson('/api/products/99999', [
            'name' => 'Ghost',
            'price' => 10,
            'stock' => 1,
        ]);

        $testResponse->assertStatus(404);
    }

    #[Test]
    final public function delete_returns_not_found_for_nonexistent_product(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:delete']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->deleteJson('/api/products/99999');

        $testResponse->assertStatus(404);
    }

    #[Test]
    final public function wildcard_token_can_access_all_resources(): void
    {
        $user = User::factory()->create();
        Product::factory()->create();

        $newAccessToken = $user->createToken('Wildcard', ['*']);

        $headers = ['Authorization' => 'Bearer '.$newAccessToken->plainTextToken];

        $this->withHeaders($headers)->getJson('/api/users')->assertStatus(200);
        $this->withHeaders($headers)->getJson('/api/products')->assertStatus(200);
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
