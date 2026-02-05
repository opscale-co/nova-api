<?php

namespace Opscale\NovaAPI\Nova\Repeatables;

use Illuminate\Support\Collection;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Repeater\Repeatable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class Ability extends Repeatable
{
    /**
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    final public function fields(NovaRequest $request): array
    {
        /** @var array<string, string> $resources */
        $resources = (new Collection(Nova::$resources))
            ->mapWithKeys(function (string $resource): array {
                /** @var class-string<\Laravel\Nova\Resource<\Illuminate\Database\Eloquent\Model>> $resource */
                return [$resource::uriKey() => $resource::singularLabel()];
            })->toArray();

        // Get current data if available
        $currentData = $this->data ?? [];

        return array_merge(parent::fields($request), [
            Select::make(__('Resource'), 'resource')
                ->options($resources)
                ->displayUsingLabels()
                ->rules('required'),

            MultiSelect::make(__('Actions'), 'actions')
                ->options([
                    'create' => __('Create'),
                    'read' => __('Read'),
                    'update' => __('Update'),
                    'delete' => __('Delete'),
                    'execute' => __('Execute'),
                ])
                ->displayUsingLabels()
                ->rules('required'),
        ]);
    }
}
