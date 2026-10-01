<?php

namespace Allandereal\FilamentApi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentApiServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-api';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasRoute('api');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentApi::class);
    }
}
