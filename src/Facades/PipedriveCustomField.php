<?php

namespace Skeylup\LaravelPipedrive\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Skeylup\LaravelPipedrive\Services\PipedriveCustomFieldService
 */
class PipedriveCustomField extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Skeylup\LaravelPipedrive\Services\PipedriveCustomFieldService::class;
    }
}
