<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Browser;

use Laravel\Dusk\Browser;
use Opscale\NovaAPI\Tests\DuskTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AccessTokenResourceTest extends DuskTestCase
{
    #[Test]
    final public function can_navigate_to_api_tokens_resource(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNova($browser)
                ->visit('/nova/resources/api-tokens')
                ->waitForText('API Tokens')
                ->assertSee('API Tokens');
        });
    }

    #[Test]
    final public function can_see_create_token_form(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNova($browser)
                ->visit('/nova/resources/api-tokens/new')
                ->waitForText('Create API Token')
                ->assertSee('Name')
                ->assertSee('Consumer')
                ->assertSee('Abilities');
        });
    }

    #[Test]
    final public function create_token_requires_consumer(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNova($browser)
                ->visit('/nova/resources/api-tokens/new')
                ->waitForText('Create API Token')
                ->type('@name', 'Incomplete Token')
                ->press('Create API Token')
                ->waitForText('The Consumer field is required')
                ->assertSee('The Consumer field is required');
        });
    }

    #[Test]
    final public function can_create_access_token_with_consumer(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNova($browser)
                ->visit('/nova/resources/api-tokens/new')
                ->waitForText('Create API Token')
                ->type('@name', 'Dusk Created Token')
                ->select('@tokenable-type', 'users')
                ->pause(500)
                ->select('@tokenable-select', '1')
                ->press('Create API Token')
                ->waitForText('Dusk Created Token', 10)
                ->assertSee('Dusk Created Token');
        });
    }
}
