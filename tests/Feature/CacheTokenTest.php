<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Opscale\NovaAPI\Events\AccessTokenGenerated;
use Opscale\NovaAPI\Services\Actions\CacheToken;
use Opscale\NovaAPI\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;

#[CoversClass(CacheToken::class)]
final class CacheTokenTest extends TestCase
{
    #[Test]
    final public function caches_token_with_correct_key(): void
    {
        Cache::flush();

        $cacheToken = new CacheToken;
        $result = $cacheToken->handle([
            'tokenId' => 'test-id-123',
            'token' => 'plain-text-token-value',
        ]);

        $this->assertEquals(['success' => true], $result);
        $this->assertEquals('plain-text-token-value', Cache::get('opscale.api.token.test-id-123'));
    }

    #[Test]
    final public function cached_token_expires_after_five_minutes(): void
    {
        Cache::flush();

        $cacheToken = new CacheToken;
        $cacheToken->handle([
            'tokenId' => 'expiry-test',
            'token' => 'expiring-token',
        ]);

        $this->assertEquals('expiring-token', Cache::get('opscale.api.token.expiry-test'));

        $this->travel(6)->minutes();

        $this->assertNull(Cache::get('opscale.api.token.expiry-test'));
    }

    #[Test]
    final public function returns_correct_identifier(): void
    {
        $cacheToken = new CacheToken;

        $this->assertSame('cache-token', $cacheToken->identifier());
    }

    #[Test]
    final public function returns_correct_name(): void
    {
        $cacheToken = new CacheToken;

        $this->assertSame('Cache Token', $cacheToken->name());
    }

    #[Test]
    final public function returns_correct_description(): void
    {
        $cacheToken = new CacheToken;

        $this->assertSame('Caches an access token for temporary retrieval', $cacheToken->description());
    }

    #[Test]
    final public function defines_required_parameters(): void
    {
        $cacheToken = new CacheToken;
        $parameters = $cacheToken->parameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('tokenId', $parameters[0]['name']);
        $this->assertEquals('token', $parameters[1]['name']);
        $this->assertContains('required', $parameters[0]['rules']);
        $this->assertContains('required', $parameters[1]['rules']);
    }

    #[Test]
    final public function as_listener_caches_token_from_event(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $newAccessToken = $user->createToken('Test Token', ['users:read']);

        $accessTokenGenerated = new AccessTokenGenerated($newAccessToken);

        $cacheToken = new CacheToken;
        $cacheToken->asListener($accessTokenGenerated);

        /** @var int|string $tokenId */
        $tokenId = $newAccessToken->accessToken->getKey();
        $cached = Cache::get('opscale.api.token.'.$tokenId);

        $this->assertEquals($newAccessToken->plainTextToken, $cached);
    }
}
