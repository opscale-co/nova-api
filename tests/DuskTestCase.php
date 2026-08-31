<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests;

use Illuminate\Foundation\Application;
use Laravel\Dusk\Browser;
use Opscale\NovaAPI\Nova\AccessToken;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\Dusk\TestCase as BaseTestCase;
use Override;
use Workbench\App\Nova\Product;
use Workbench\App\Nova\User;

abstract class DuskTestCase extends BaseTestCase
{
    use WithWorkbench;

    protected static $baseServePort = 8089;

    /**
     * Login to Nova via browser using the seeded admin user.
     */
    final protected function loginToNova(Browser $browser): Browser
    {
        $browser->visit('/nova');

        if ($browser->element('input[name="email"]')) {
            $browser->type('email', 'admin@laravel.com')
                ->type('password', 'password')
                ->press('Log In')
                ->waitForText('Get Started');
        }

        return $browser;
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');

        $app['config']->set('nova-api.resources', [
            User::class,
            Product::class,
            AccessToken::class,
        ]);
    }
}
