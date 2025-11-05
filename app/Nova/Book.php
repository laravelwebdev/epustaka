<?php

namespace App\Nova;

use App\Nova\Actions\DownloadBook;
use App\Nova\Actions\DownloadPdf;
use App\Nova\Metrics\BooksCount;
use App\Nova\Metrics\Points;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Book extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Book>
     */
    public static $model = \App\Models\Book::class;

    public static $with = ['users'];

    public static $perPageOptions = [5, 10];

    public static function label()
    {
        return 'Unduh Buku';
    }

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'book_title';

    public function subtitle()
    {
        return $this->book_author;
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'book_title',
        'book_author',
        'category_name',
        'publisher',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Image::make('Cover Buku')
                ->preview(fn () => $this->cover_url)
                ->indexWidth(80)
                ->detailWidth(80)
                ->exceptOnForms(),
            Text::make('Title', 'book_title')
                ->sortable()
                ->exceptOnForms(),
            Text::make('Author', 'book_author')
                ->sortable()
                ->exceptOnForms(),
            Text::make('Category', 'category_name')
                ->sortable()
                ->exceptOnForms(),
            Text::make('Publisher', 'publisher')
                ->sortable()
                ->exceptOnForms(),
            Text::make('Published date', 'publish_date')
                ->sortable()
                ->exceptOnForms(),
            Text::make('Description', 'book_description')
                ->asHtml()
                ->onlyOnDetail(),
            Text::make('File Size', 'file_size_info')
                ->onlyOnDetail(),
            Text::make('File Extension', 'file_ext')
                ->onlyOnDetail(),
            Text::make('language', 'language')
                ->onlyOnDetail(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [
            Points::make()
                ->width('1/2')
                ->refreshWhenActionsRun(),
            BooksCount::make()
                ->width('1/2')
                ->refreshWhenActionsRun(),
        ];
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
        return [
            DownloadBook::make()->standalone()
                ->confirmText('Pinjam buku dari iPusnas?')
                ->size('7xl'),
            DownloadPdf::make()->sole()
                ->confirmText('Unduh Buku?'),
        ];
    }
}
