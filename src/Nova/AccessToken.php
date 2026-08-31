<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Nova;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Repeater;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Laravel\Sanctum\HasApiTokens;
use Opscale\NovaAPI\Models\AccessToken as Model;
use Opscale\NovaAPI\Nova\Presets\AbilityPreset;
use Opscale\NovaAPI\Nova\Repeatables\Ability;
use Opscale\NovaAPI\Services\Actions\ConsumeToken;

/**
 * @extends resource<Model>
 */
class AccessToken extends Resource
{
    /**
     * @var class-string<Model>
     */
    public static string $model = Model::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array<int, string>
     */
    public static $search = [
        'name',
    ];

    final public static function label(): string
    {
        return __('API Tokens');
    }

    final public static function singularLabel(): string
    {
        return __('API Token');
    }

    final public static function uriKey(): string
    {
        return __('api-tokens');
    }

    /**
     * @return array<string, Field>
     */
    final public function defaultFields(NovaRequest $novaRequest): array
    {
        return [
            'name' => Text::make(__('Name'), 'name')
                ->rules(fn (): array => $this->model()?->validationRules['name'] ?? [])
                ->sortable(),

            'expires_at' => Date::make(__('Expiration'), 'expires_at')
                ->nullable()
                ->sortable()
                ->filterable(),

            'last_used_at' => DateTime::make(__('Last used'), 'last_used_at')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            'created_at' => DateTime::make(__('Created'), 'created_at')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            'tokenable' => MorphTo::make(__('Consumer'), 'tokenable')
                ->types($this->getTokenableResources())
                ->onlyOnForms()
                ->required(),

            'abilities' => Repeater::make(__('Abilities'), 'abilities')
                ->repeatables([
                    Ability::make(),
                ])
                ->preset(new AbilityPreset())
                ->onlyOnForms()
                ->rules(fn (): array => $this->model()?->validationRules['abilities'] ?? []),
        ];
    }

    /**
     * @return array<int, Field>
     */
    final public function fields(NovaRequest $request): array
    {
        return array_values(static::defaultFields($request));
    }

    /**
     * @return array<int, Field>
     */
    final public function fieldsForDetail(NovaRequest $novaRequest): array
    {
        $fields = array_values(static::defaultFields($novaRequest));
        $fields[] = Text::make(__('Token'),
            function (): string {
                if (! $this->resource) {
                    return '';
                }

                /** @var int|string $key */
                $key = $this->resource->getKey();

                $token = ConsumeToken::run(['tokenId' => (string) $key])->data()['token'] ?? '';

                return is_string($token) ? $token : '';
            })
            ->copyable()
            ->onlyOnDetail()
            ->canSee(function (Request $request): bool {
                if (! $this->resource) {
                    return false;
                }

                /** @var int|string $key */
                $key = $this->resource->getKey();

                $token = ConsumeToken::run(['tokenId' => (string) $key])->data()['token'] ?? '';

                return is_string($token) && $token !== '';
            });

        return $fields;
    }

    /**
     * Get Nova resources that can create tokens (have HasApiTokens trait)
     *
     * @return array<class-string<\Laravel\Nova\Resource<\Illuminate\Database\Eloquent\Model>>, string>
     */
    private function getTokenableResources(): array
    {
        /** @var array<class-string<\Laravel\Nova\Resource<\Illuminate\Database\Eloquent\Model>>, string> $resources */
        $resources = (new Collection(Nova::$resources))
            ->filter(function (string $resource): bool {
                /** @var class-string<\Laravel\Nova\Resource> $resource */
                /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
                $model = $resource::$model;
                $traits = class_uses_recursive($model);

                return in_array(HasApiTokens::class, $traits ?: [], true);
            })
            ->toArray();

        return $resources;
    }
}
