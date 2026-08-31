<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Opscale\NovaAPI\Events\AccessTokenGenerated;
use Opscale\NovaAPI\Models\AccessToken;
use Opscale\NovaAPI\Services\Actions\CacheToken;
use Opscale\NovaAPI\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;

#[CoversClass(AccessTokenGenerated::class)]
final class AccessTokenGeneratedTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    final public function event_holds_new_access_token(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test Token', ['users:read']);

        $accessTokenGenerated = new AccessTokenGenerated($newAccessToken);

        $this->assertSame($newAccessToken, $accessTokenGenerated->newAccessToken);
        $this->assertEquals('Test Token', $accessTokenGenerated->newAccessToken->accessToken->name);
        $this->assertNotEmpty($accessTokenGenerated->newAccessToken->plainTextToken);
    }

    #[Test]
    final public function event_is_dispatched_when_token_created_via_repository(): void
    {
        Event::fake([AccessTokenGenerated::class]);

        $user = User::factory()->create();

        $accessToken = new AccessToken;
        $accessToken->name = 'Event Test Token';
        $accessToken->tokenable()->associate($user);
        $accessToken->save();

        Event::assertDispatched(AccessTokenGenerated::class, function (AccessTokenGenerated $accessTokenGenerated): bool {
            return $accessTokenGenerated->newAccessToken->accessToken->name === 'Event Test Token';
        });
    }

    #[Test]
    final public function cache_token_action_handles_event_via_as_listener(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Listener Test', ['products:read']);

        $accessTokenGenerated = new AccessTokenGenerated($newAccessToken);

        $cacheToken = new CacheToken;
        $cacheToken->asListener($accessTokenGenerated);

        /** @var int|string $tokenId */
        $tokenId = $newAccessToken->accessToken->getKey();
        $cached = Cache::get('opscale.api.token.'.$tokenId);

        $this->assertEquals($newAccessToken->plainTextToken, $cached);
    }

    #[Test]
    final public function event_contains_plain_text_token(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Plain Text Test', ['users:read']);

        $accessTokenGenerated = new AccessTokenGenerated($newAccessToken);

        $this->assertStringContainsString('|', $accessTokenGenerated->newAccessToken->plainTextToken);
    }

    #[Test]
    final public function event_contains_access_token_model(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Model Test', ['users:read']);

        $accessTokenGenerated = new AccessTokenGenerated($newAccessToken);

        /** @var int $id */
        $id = $accessTokenGenerated->newAccessToken->accessToken->id;
        $this->assertGreaterThan(0, $id);
        $this->assertEquals(['users:read'], $accessTokenGenerated->newAccessToken->accessToken->abilities);
    }
}
