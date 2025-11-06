<?php

namespace App\Nova;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BulkDownload extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\BulkDownload>
     */
    public static $model = \App\Models\BulkDownload::class;

    public static function label()
    {
        return 'Bulk Download';
    }

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    public function subtitle()
    {
        return $this->id;
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'category_name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Category ID', 'category_id')
                ->rules('required', 'max:255'),
            Text::make('Category Name', 'category_name')
                ->rules('required', 'max:255'),
            Number::make('Offset', 'offset')
                ->rules('required', 'min:0')
                ->exceptOnForms(),
            Boolean::make('Is Active', 'is_active')
                ->rules('required'),
            Boolean::make('Is Completed', 'is_completed')
                ->exceptOnForms(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
