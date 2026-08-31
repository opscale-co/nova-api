<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Opscale\NovaAPI\Services\Actions\ConsumeToken;
use Opscale\NovaAPI\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ConsumeToken::class)]
final class ConsumeTokenTest extends TestCase
{
    #[Test]
    final public function retrieves_cached_token(): void
    {
        Cache::put('opscale.api.token.test-id', 'cached-token-value', 300);

        $consumeToken = new ConsumeToken;
        $result = $consumeToken->handle(['tokenId' => 'test-id']);

        $this->assertSame(['token' => 'cached-token-value'], $result);
    }

    #[Test]
    final public function returns_empty_string_for_missing_token(): void
    {
        Cache::flush();

        $consumeToken = new ConsumeToken;
        $result = $consumeToken->handle(['tokenId' => 'nonexistent-id']);

        $this->assertSame(['token' => ''], $result);
    }

    #[Test]
    final public function returns_correct_identifier(): void
    {
        $consumeToken = new ConsumeToken;

        $this->assertSame('consume-token', $consumeToken->identifier());
    }

    #[Test]
    final public function returns_correct_name(): void
    {
        $consumeToken = new ConsumeToken;

        $this->assertSame('Consume Token', $consumeToken->name());
    }

    #[Test]
    final public function returns_correct_description(): void
    {
        $consumeToken = new ConsumeToken;

        $this->assertSame('Retrieves a cached token by its ID', $consumeToken->description());
    }

    #[Test]
    final public function defines_required_parameters(): void
    {
        $consumeToken = new ConsumeToken;
        $parameters = $consumeToken->parameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('tokenId', $parameters[0]['name']);
        $this->assertContains('required', $parameters[0]['rules']);
    }

    #[Test]
    final public function returns_empty_for_expired_cached_token(): void
    {
        Cache::put('opscale.api.token.expire-test', 'token-value', 300);

        $this->travel(6)->minutes();

        $consumeToken = new ConsumeToken;
        $result = $consumeToken->handle(['tokenId' => 'expire-test']);

        $this->assertSame(['token' => ''], $result);
    }
}
