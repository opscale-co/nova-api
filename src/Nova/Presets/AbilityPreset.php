<?php

namespace Opscale\NovaAPI\Nova\Presets;

use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\Repeater\Presets\Preset;
use Laravel\Nova\Fields\Repeater\RepeatableCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Support\Fluent;

class AbilityPreset implements Preset
{
    /**
     * Save the field value to permanent storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Laravel\Nova\Support\Fluent  $model
     */
    public function set(
        NovaRequest $request,
        string $requestAttribute,
        $model,
        string $attribute,
        RepeatableCollection $repeatables,
        string|int|null $uniqueField
    ): callable {
        $repeaterItemsInput = collect($request->input($requestAttribute));

        // Process each repeater item and collect field callbacks
        $callbacks = collect($repeaterItemsInput)
            ->map(function ($item, $itemIndex) use ($request, $requestAttribute, $repeatables) {
                $repeatable = $repeatables->findByKey($item['type']);
                $fields = FieldCollection::make($repeatable->fields($request));

                // Process field callbacks for validation/transformation
                $fieldsCallbacks = $fields
                    ->withoutUnfillable()
                    ->withoutMissingValues()
                    ->map(
                        static fn (Field $field) => $field->fillInto(
                            $request,
                            new Fluent(),
                            $field->attribute,
                            "{$requestAttribute}.{$itemIndex}.fields.{$field->attribute}"
                        )
                    )
                    ->filter(static fn ($callback) => \is_callable($callback));

                return static function () use ($fieldsCallbacks) {
                    $fieldsCallbacks->each->__invoke();
                };
            });

        // Transform repeater structure to flat abilities array
        $abilities = $repeaterItemsInput
            ->flatMap(function ($item) {
                $resource = $item['fields']['resource'] ?? null;
                $actions = $item['fields']['actions'] ?? [];

                if (! $resource || empty($actions)) {
                    return [];
                }

                // Convert to "resource:action" format
                return collect($actions)->map(fn ($action) => "{$resource}:{$action}");
            })
            ->unique()
            ->values()
            ->all();

        // Set the abilities attribute on the model
        $model->setAttribute($attribute, $abilities);

        // Return callback that invokes all field callbacks
        return static function () use ($callbacks) {
            $callbacks->each->__invoke();
        };
    }

    /**
     * Retrieve the value from storage and hydrate the field's value.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Laravel\Nova\Support\Fluent  $model
     */
    public function get(NovaRequest $request, $model, string $attribute, RepeatableCollection $repeatables): Collection
    {
        $abilities = $model->{$attribute};

        // Handle null or empty abilities
        if (empty($abilities)) {
            return new Collection([]);
        }

        // Ensure abilities is an array
        if (! is_array($abilities)) {
            $abilities = [];
        }

        // Group abilities by resource
        $groupedAbilities = collect($abilities)
            ->map(function ($ability) {
                // Parse "resource:action" format
                $parts = explode(':', $ability, 2);

                return [
                    'resource' => $parts[0] ?? null,
                    'action' => $parts[1] ?? null,
                ];
            })
            ->filter(fn ($item) => $item['resource'] !== null && $item['action'] !== null)
            ->groupBy('resource')
            ->map(function ($items, $resource) {
                $actions = $items->pluck('action')->values()->all();
                
                return [
                    'resource' => $resource,
                    'actions' => array_values($actions), // Ensure numeric array keys
                ];
            });

        // Debug: uncomment to see the data structure
        // dd([
        //     'raw_abilities' => $abilities,
        //     'grouped' => $groupedAbilities->toArray(),
        // ]);

        // Transform to repeater format
        return RepeatableCollection::make(
            $groupedAbilities->map(function ($group) use ($repeatables) {
                return $repeatables->newRepeatableByKey('ability', [
                    'resource' => $group['resource'],
                    'actions' => $group['actions'],
                ]);
            })->values()
        );
    }
}
