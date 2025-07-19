<?php

namespace Skeylup\LaravelPipedrive\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Skeylup\LaravelPipedrive\LaravelPipedrive
 */
class LaravelPipedrive extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Skeylup\LaravelPipedrive\LaravelPipedrive::class;
    }
}
