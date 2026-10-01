<?php

namespace Allandereal\FilamentApi;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use LogicException;

class FilamentApi
{
    /**
     * @return array<Panel>
     */
    public function getPanels(): array
    {
        return array_filter(
            Filament::getPanels(),
            fn (Panel $panel): bool => $panel->hasPlugin(app(FilamentApiPlugin::class)->getId()),
        );
    }

    public function getPlugin(Panel $panel): FilamentApiPlugin
    {
        /** @var FilamentApiPlugin $plugin */
        $plugin = $panel->getPlugin(app(FilamentApiPlugin::class)->getId());

        return $plugin;
    }

    /**
     * @return array<string, class-string<\Filament\Resources\Resource>>
     */
    public function getResourceEndpoints(Panel $panel): array
    {
        $endpoints = [];

        foreach ($this->getPlugin($panel)->getResources($panel) as $resource) {
            $slug = $this->getResourceSlug($resource);

            if (array_key_exists($slug, $endpoints)) {
                throw new LogicException("The resources [{$endpoints[$slug]}] and [{$resource}] both use the API endpoint [{$slug}].");
            }

            $endpoints[$slug] = $resource;
        }

        foreach (array_keys($this->getPlugin($panel)->getModels()) as $slug) {
            if (array_key_exists($slug, $endpoints)) {
                throw new LogicException("The API endpoint [{$slug}] is used by both the resource [{$endpoints[$slug]}] and a model.");
            }
        }

        return $endpoints;
    }

    /**
     * The endpoint mirrors the resource's URL in the panel, e.g. `shop/orders` or `{cluster}/{resource}`.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public function getResourceSlug(string $resource): string
    {
        $slug = $resource::getSlug();

        if ($cluster = $resource::getCluster()) {
            $slug = $cluster::getSlug() . '/' . $slug;
        }

        return trim($slug, '/');
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return array<string, class-string<RelationManager>>
     */
    public function getRelationManagers(string $resource): array
    {
        $managers = [];

        foreach ($resource::getRelations() as $manager) {
            $groupManagers = ($manager instanceof RelationGroup)
                ? invade($manager)->managers /** @phpstan-ignore-line */
                : [$manager];

            foreach ($groupManagers as $groupManager) {
                if ($groupManager instanceof RelationManagerConfiguration) {
                    $groupManager = $groupManager->relationManager;
                }

                $managers[$groupManager::getRelationshipName()] = $groupManager;
            }
        }

        return $managers;
    }
}
