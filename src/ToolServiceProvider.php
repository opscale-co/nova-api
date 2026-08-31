<?php

declare(strict_types=1);

namespace Opscale\NovaAPI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Opscale\NovaAPI\Console\Commands\SyncResources;
use Opscale\NovaAPI\Events\AccessTokenGenerated;
use Opscale\NovaAPI\Http\Controllers\APIController;
use Opscale\NovaAPI\Http\Requests\APIRequest;
use Opscale\NovaAPI\Nova\AccessToken;
use Opscale\NovaAPI\Policies\APIPolicy;
use Opscale\NovaAPI\Services\Actions\CacheToken;
use Opscale\NovaPackageTools\NovaPackage;
use Opscale\NovaPackageTools\NovaPackageServiceProvider;
use Orion\Facades\Orion;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;

class ToolServiceProvider extends NovaPackageServiceProvider
{
    /**
     * @phpstan-ignore solid.ocp.conditionalOverride
     */
    public function configurePackage(Package $package): void
    {
        /** @var NovaPackage $package */
        $package
            ->name('nova-api')
            /** @phpstan-ignore argument.type */
            ->hasResources([
                AccessToken::class,
            ])
            ->hasConfigFile('nova-api')
            ->hasCommand(SyncResources::class)
            ->hasInstallCommand(function (InstallCommand $installCommand): void {
                $installCommand
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('opscale-co/nova-api');
            });
    }

    final public function packageBooted(): void
    {
        parent::packageBooted();
        $this->registerAPIRoutes();
        $this->registerAPIRequests();
        $this->registerAPIPolicies();
        $this->registerEventListeners();
    }

    final public function registerAPIRoutes(): void
    {
        /** @var array<int, class-string<\Laravel\Nova\Resource<Model>>> $configuredResources */
        $configuredResources = config('nova-api.resources', []);

        /** @var Collection<int, class-string<\Laravel\Nova\Resource<Model>>> $resources */
        $resources = new Collection($configuredResources);

        Route::prefix('api')->middleware(['auth:sanctum'])->group(function () use ($resources): void {
            foreach ($resources as $resource) {
                /** @var class-string<\Laravel\Nova\Resource<Model>> $resource */
                Orion::resource($resource::uriKey(), APIController::class);
            }
        });
    }

    final public function registerAPIRequests(): void
    {
        /** @var array<int, class-string<\Laravel\Nova\Resource<Model>>> $configuredResources */
        $configuredResources = config('nova-api.resources', []);

        /** @var Collection<int, class-string<\Laravel\Nova\Resource<Model>>> $resources */
        $resources = new Collection($configuredResources);

        foreach ($resources as $resource) {
            $class = get_class(new class extends APIRequest
            {
                protected ?string $resource = null;

                public function getResource(): string
                {
                    return $this->resource ?? '';
                }

                public function setResource(string $resource): void
                {
                    $this->resource = $resource;
                }
            });

            $binding = $resource::uriKey().'-request';
            $this->app->bindIf($binding, function ($app) use ($class, $resource): object {
                $instance = new $class;
                $instance->setResource($resource);

                return $instance;
            });
        }

    }

    final public function registerAPIPolicies(): void
    {
        /** @var array<int, class-string<\Laravel\Nova\Resource<Model>>> $configuredResources */
        $configuredResources = config('nova-api.resources', []);

        /** @var Collection<int, class-string<\Laravel\Nova\Resource<Model>>> $resources */
        $resources = new Collection($configuredResources);

        foreach ($resources as $resource) {
            $class = get_class(new class extends APIPolicy
            {
                protected ?string $resource = null;

                public function getResource(): string
                {
                    return $this->resource ?? '';
                }

                public function setResource(string $resource): void
                {
                    $this->resource = $resource;
                }
            });

            $binding = $resource::uriKey().'-policy';
            $this->app->bindIf($binding, function ($app) use ($class, $resource): object {
                $instance = new $class;
                $instance->setResource($resource::uriKey());

                return $instance;
            });
        }
    }

    /**
     * Register event listeners for the package
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            AccessTokenGenerated::class,
            [CacheToken::class, 'asListener']
        );
    }
}
