<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Pages\ApiLogs;
use Allandereal\FilamentApi\Pages\ApiTokens;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']));

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->plugin = FilamentApi::getPlugin(Filament::getPanel('admin'));
});

it('groups the API pages under "API"', function () {
    expect(ApiTokens::getNavigationGroup())->toBe('API')
        ->and(ApiLogs::getNavigationGroup())->toBe('API')
        ->and(ApiTokens::getNavigationSort())->toBeLessThan(ApiLogs::getNavigationSort());

    $this->get(ApiTokens::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['API', 'API tokens', 'API logs']);
});

it('renames the group', function () {
    $this->plugin->navigationGroup('Integrations');

    expect(ApiTokens::getNavigationGroup())->toBe('Integrations')
        ->and(ApiLogs::getNavigationGroup())->toBe('Integrations');
});

it('can put the pages outside of a group', function () {
    $this->plugin->navigationGroup(null);

    expect(ApiTokens::getNavigationGroup())->toBeNull()
        ->and(ApiLogs::getNavigationGroup())->toBeNull();
});

it('lets each page have its own group', function () {
    $this->plugin->tokensNavigationGroup('Account')->logsNavigationGroup(null);

    expect(ApiTokens::getNavigationGroup())->toBe('Account')
        ->and(ApiLogs::getNavigationGroup())->toBeNull();
});
