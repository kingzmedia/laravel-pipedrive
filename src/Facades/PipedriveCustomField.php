<?php

namespace Keggermont\LaravelPipedrive\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService
 */
class PipedriveCustomField extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService::class;
    }
}
