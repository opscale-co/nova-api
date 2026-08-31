<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Opscale\NovaAPI\Events\AccessTokenGenerated;
use Opscale\NovaAPI\Models\AccessToken;
use Opscale\NovaAPI\Models\Repositories\AccessTokenRepository;
use Opscale\NovaAPI\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;

#[CoversClass(AccessTokenRepository::class)]
final class AccessTokenRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function dispatches_event_when_token_is_created(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'Test Token';
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        Event::assertDispatched(AccessTokenGenerated::class);
    }

    #[Test]
    final public function creates_token_with_wildcard_abilities_when_none_specified(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'No Abilities Token';
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        /** @var AccessToken $storedToken */
        $storedToken = AccessToken::query()->find($accessToken->getKey());

        $this->assertEquals(['*'], $storedToken->abilities);
    }

    #[Test]
    final public function creates_token_with_structured_abilities(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'Structured Abilities Token';
        $accessToken->abilities = [
            ['fields' => ['resource' => 'users', 'actions' => ['read', 'create']]],
            ['fields' => ['resource' => 'products', 'actions' => ['read']]],
        ];
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        /** @var AccessToken $storedToken */
        $storedToken = AccessToken::query()->find($accessToken->getKey());

        /** @var array<int, string> $abilities */
        $abilities = $storedToken->abilities;
        $this->assertContains('users:read', $abilities);
        $this->assertContains('users:create', $abilities);
        $this->assertContains('products:read', $abilities);
    }

    #[Test]
    final public function does_not_set_id_when_tokenable_is_null(): void
    {
        $accessToken = new AccessToken;
        $accessToken->name = 'Orphan Token';

        // insertAndSetId returns false, but Eloquent save() still returns true
        // The important assertion is that no id is set
        $accessToken->save();

        $this->assertNull($accessToken->getKey());
    }

    #[Test]
    final public function sets_id_after_creation(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'ID Token';
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        $this->assertNotNull($accessToken->getKey());
    }

    #[Test]
    final public function creates_token_with_expiration(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();
        $expiresAt = now()->addHour();

        $accessToken = new AccessToken;
        $accessToken->name = 'Expiring Token';
        $accessToken->expires_at = $expiresAt;
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        /** @var AccessToken $storedToken */
        $storedToken = AccessToken::query()->find($accessToken->getKey());

        /** @var Carbon $expiresAtStored */
        $expiresAtStored = $storedToken->expires_at;
        $this->assertEquals(
            $expiresAt->format('Y-m-d H:i:s'),
            $expiresAtStored->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    final public function dispatched_event_contains_token_name(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'Named Token';
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        Event::assertDispatched(AccessTokenGenerated::class, function (AccessTokenGenerated $accessTokenGenerated): bool {
            return $accessTokenGenerated->newAccessToken->accessToken->name === 'Named Token';
        });
    }
}
