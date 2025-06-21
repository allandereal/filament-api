<?php

namespace Allandereal\FilamentApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Allandereal\FilamentApi\FilamentApi
 */
class FilamentApi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Allandereal\FilamentApi\FilamentApi::class;
    }
}
