<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Services\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Opscale\Actions\Action;
use Opscale\NovaAPI\Events\AccessTokenGenerated;

class CacheToken extends Action
{
    final public function identifier(): string
    {
        return 'cache-token';
    }

    final public function name(): string
    {
        return 'Cache Token';
    }

    final public function description(): string
    {
        return 'Caches an access token for temporary retrieval';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, string>}>
     */
    final public function parameters(): array
    {
        return [
            [
                'name' => 'tokenId',
                'description' => 'The unique identifier of the token',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
            [
                'name' => 'token',
                'description' => 'The plain text token to cache',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * The pipeline (execute) has already filled and validated the inputs
     * against parameters() before handle() runs, so we trust $inputs here.
     *
     * @param  array{tokenId?: string, token?: string}  $inputs
     * @return array<string, bool>
     */
    final public function handle(array $inputs = []): array
    {
        Cache::put(
            'opscale.api.token.'.($inputs['tokenId'] ?? ''),
            $inputs['token'] ?? '',
            Carbon::now()->addMinutes(5)
        );

        return ['success' => true];
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, string>}>
     */
    final public function outputs(): array
    {
        return [
            [
                'name' => 'success',
                'description' => 'Whether the token was cached successfully',
                'type' => 'boolean',
                'rules' => ['required', 'boolean'],
            ],
        ];
    }

    final public function asListener(AccessTokenGenerated $accessTokenGenerated): void
    {
        /** @var int|string $id */
        $id = $accessTokenGenerated->newAccessToken->accessToken->getKey();
        /** @var string $token */
        $token = $accessTokenGenerated->newAccessToken->plainTextToken;

        static::run([
            'tokenId' => (string) $id,
            'token' => $token,
        ]);
    }
}
