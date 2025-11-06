<?php

namespace App\Nova;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;

class AutoBorrow extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AutoBorrow>
     */
    public static $model = \App\Models\AutoBorrow::class;

    public static function label()
    {
        return 'Auto Borrow';
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
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('iPusnas Book ID', 'ipusnas_book_id')
                ->sortable()
                ->rules('nullable', 'string', 'max:255')
                ->help('The unique identifier of the book in the iPusnas system.'),
            Boolean::make('Borrowed', 'borrowed')
                ->sortable()
                ->exceptOnForms(),
            URL::make('Lihat', fn () => 'https://ipusnas2.perpusnas.go.id/book/'.$this->ipusnas_book_id),
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
