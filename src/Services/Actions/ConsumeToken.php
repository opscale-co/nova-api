<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Services\Actions;

use Illuminate\Support\Facades\Cache;
use Opscale\Actions\Action;

class ConsumeToken extends Action
{
    final public function identifier(): string
    {
        return 'consume-token';
    }

    final public function name(): string
    {
        return 'Consume Token';
    }

    final public function description(): string
    {
        return 'Retrieves a cached token by its ID';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, string>}>
     */
    final public function parameters(): array
    {
        return [
            [
                'name' => 'tokenId',
                'description' => 'The unique identifier of the token to retrieve',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * The pipeline (execute) has already filled and validated the inputs
     * against parameters() before handle() runs, so we trust $inputs here.
     *
     * @param  array{tokenId?: string}  $inputs
     * @return array{token: string}
     */
    final public function handle(array $inputs = []): array
    {
        /** @var string $token */
        $token = Cache::get('opscale.api.token.'.($inputs['tokenId'] ?? ''), '');

        return ['token' => $token];
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, string>}>
     */
    final public function outputs(): array
    {
        return [
            [
                'name' => 'token',
                'description' => 'The plain text token retrieved from cache, or an empty string when not found',
                'type' => 'string',
                'rules' => ['present', 'string'],
            ],
        ];
    }
}
