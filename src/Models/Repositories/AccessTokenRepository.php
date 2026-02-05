<?php

namespace Opscale\NovaAPI\Models\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Opscale\NovaAPI\Events\AccessTokenGenerated;

trait AccessTokenRepository
{
    /**
     * Overriding insertAndSetId method to create a token via Sanctum API
     * to keep in sync the Laravel Nova model with the Sanctum model
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  array<string, mixed>  $attributes
     *
     * @phpstan-ignore method.childParameterType, solid.ocp.conditionalOverride
     */
    protected function insertAndSetId(Builder $builder, $attributes): bool
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $tokenableModel */
        $tokenableModel = $this->tokenable;

        if ($tokenableModel === null || ! method_exists($tokenableModel, 'createToken')) {
            return false;
        }

        /** @var \Laravel\Sanctum\NewAccessToken|null $token */
        $token = null;

        /** @var array<array{fields: array{resource: string, actions: array<string>}}>|array<string>|null $abilities */
        $abilities = $this->getAttribute('abilities');

        if ($abilities) {
            // Check if abilities is already in flat format (from preset)
            if (isset($abilities[0]) && is_string($abilities[0])) {
                // Already in "resource:action" format
                /** @var array<string> $abilityList */
                $abilityList = $abilities;
            } else {
                // Legacy format: transform from repeater structure
                /** @var array<string> $abilityList */
                $abilityList = (new Collection($abilities))->flatMap(function (array $item): Collection {
                    /** @var array{resource: string, actions: array<string>} $fields */
                    $fields = $item['fields'];
                    /** @var array<string> $actions */
                    $actions = $fields['actions'];
                    /** @var string $resource */
                    $resource = $fields['resource'];

                    return (new Collection($actions))
                        ->map(function (string $action) use ($resource): string {
                            return strtolower($resource) . ':' . $action;
                        });
                })->all();
            }
        } else {
            $abilityList = ['*'];
        }

        /** @var string $name */
        $name = $this->getAttribute('name') ?? '';
        /** @var DateTimeInterface|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');

        $token = $tokenableModel->createToken(
            $name,
            $abilityList,
            $expiresAt);

        $id = $token->accessToken->id;
        $keyName = $this->getKeyName();
        $this->setAttribute($keyName, $id);

        // Dispatch event with the generated token information
        AccessTokenGenerated::dispatch($token);

        return true;
    }
}
