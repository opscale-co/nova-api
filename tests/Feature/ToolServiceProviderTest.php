<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\NovaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Opscale\NovaAPI\Events\AccessTokenGenerated;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Tests\TestCase;
use Opscale\NovaAPI\ToolServiceProvider;
use Orion\OrionServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;
use Workbench\App\Nova\Product;

#[CoversClass(ToolServiceProvider::class)]
final class ToolServiceProviderTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function registers_api_routes_for_configured_resources(): void
    {
        $routeCollection = Route::getRoutes();

        $userRouteFound = false;
        $productRouteFound = false;

        /** @var \Illuminate\Routing\Route $route */
        foreach ($routeCollection->getRoutes() as $route) {
            $uri = $route->uri();
            if (str_contains($uri, 'api/users')) {
                $userRouteFound = true;
            }

            if (str_contains($uri, 'api/products')) {
                $productRouteFound = true;
            }
        }

        $this->assertTrue($userRouteFound, 'User API routes should be registered');
        $this->assertTrue($productRouteFound, 'Product API routes should be registered');
    }

    #[Test]
    final public function routes_require_sanctum_authentication(): void
    {
        $testResponse = $this->getJson('/api/users');

        $testResponse->assertStatus(401);
    }

    #[Test]
    final public function authenticated_routes_are_accessible(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test', ['users:read']);

        $testResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken->plainTextToken,
        ])->getJson('/api/users');

        $testResponse->assertStatus(200);
    }

    #[Test]
    final public function config_file_is_published(): void
    {
        $config = config('nova-api');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('resources', $config);
    }

    #[Test]
    final public function event_listener_is_registered(): void
    {
        $dispatcher = app('events');
        $listeners = $dispatcher->getListeners(AccessTokenGenerated::class);

        $this->assertNotEmpty($listeners, 'AccessTokenGenerated should have at least one listener');
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
