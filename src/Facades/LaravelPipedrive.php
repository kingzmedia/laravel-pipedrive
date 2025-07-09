<?php

namespace Keggermont\LaravelPipedrive\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Keggermont\LaravelPipedrive\LaravelPipedrive
 */
class LaravelPipedrive extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Keggermont\LaravelPipedrive\LaravelPipedrive::class;
    }
}
