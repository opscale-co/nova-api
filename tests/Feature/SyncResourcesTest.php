<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\PendingCommand;
use Laravel\Nova\NovaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Opscale\NovaAPI\Console\Commands\SyncResources;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Tests\TestCase;
use Opscale\NovaAPI\ToolServiceProvider;
use Orion\OrionServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Nova\Product;
use Workbench\App\Nova\User;

#[CoversClass(SyncResources::class)]
final class SyncResourcesTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function command_discovers_nova_resources(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('nova-api:sync-resources');
        $command->assertSuccessful();
    }

    #[Test]
    final public function command_filters_resources_by_name(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('nova-api:sync-resources', [
            '--filter' => ['User'],
        ]);
        $command->assertSuccessful();
    }

    #[Test]
    final public function command_excludes_resources_by_name(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('nova-api:sync-resources', [
            '--exclude' => ['AccessToken'],
        ]);
        $command->assertSuccessful();
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
        $app['config']->set('nova-api.resources', [
            User::class,
            Product::class,
            AccessToken::class,
        ]);
    }
}
