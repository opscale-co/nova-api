<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\NovaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Opscale\NovaAPI\Http\Requests\APIRequest;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Tests\TestCase;
use Opscale\NovaAPI\ToolServiceProvider;
use Orion\OrionServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;
use Workbench\App\Nova\Product;

#[CoversClass(APIRequest::class)]
final class APIRequestTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function validates_user_model_rules_on_create(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['users:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/users', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $testResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    #[Test]
    final public function validates_product_model_rules_on_create(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/products', [
            'name' => '',
            'price' => -10,
            'stock' => -5,
        ]);

        $testResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    final public function passes_validation_with_valid_data(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['products:create']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->postJson('/api/products', [
            'name' => 'Valid Product',
            'price' => 49.99,
            'stock' => 100,
        ]);

        $testResponse->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Valid Product']);
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
            Product::class,
            AccessToken::class,
        ]);
    }
}
