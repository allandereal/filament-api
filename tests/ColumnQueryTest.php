<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Comment;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Secret;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Team;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    actingAs($this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']));

    $post = function (string $title, string $status, int $views, string $createdAt, ?string $body = null): Post {
        $post = Post::create(['title' => $title, 'status' => $status, 'views' => $views, 'body' => $body, 'secret_note' => "note {$title}"]);
        $post->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

        return $post;
    };

    $this->jan = $post('Jan', 'draft', 10, '2025-01-15 09:30:00', 'Text');
    $this->jan2 = $post('Jan 2', 'published', 30, '2025-01-20 18:00:00');
    $this->mar = $post('Mar', 'published', 50, '2025-03-02 12:00:00', 'Text');
    $this->next = $post('Next year', 'locked', 100, '2026-02-01 00:00:00');
});

function totals(string $query): int
{
    return getJson("api/posts?per_page=5&{$query}")->assertOk()->json('meta.total');
}

describe('operator filters', function () {
    it('supports every operator', function () {
        expect(totals('where[views][eq]=30'))->toBe(1)
            ->and(totals('where[views][ne]=30'))->toBe(3)
            ->and(totals('where[views][gt]=30'))->toBe(2)
            ->and(totals('where[views][gte]=30'))->toBe(3)
            ->and(totals('where[views][lt]=30'))->toBe(1)
            ->and(totals('where[views][lte]=30'))->toBe(2)
            ->and(totals('where[status][in]=draft,locked'))->toBe(2)
            ->and(totals('where[status][in][]=draft&where[status][in][]=locked'))->toBe(2)
            ->and(totals('where[status][notin]=draft,locked'))->toBe(2)
            ->and(totals('where[created_at][between]=2025-01-01 00:00:00,2025-01-31 23:59:59'))->toBe(2)
            ->and(totals('where[body][null]=1'))->toBe(2)
            ->and(totals('where[body][notnull]=1'))->toBe(2)
            ->and(totals('where[title][like]=Jan%'))->toBe(2);
    });

    it('combines constraints', function () {
        expect(totals('where[views][gte]=20&where[views][lt]=100&where[status][eq]=published'))->toBe(2);
    });

    it('applies inside the table query', function () {
        // The `drafts` tab and the `has_body` filter still apply.
        expect(totals('tab=drafts&where[views][gte]=0'))->toBe(1)
            ->and(totals('filter[has_body]=1&where[views][gte]=20'))->toBe(1);
    });

    it('rejects hidden and unknown columns', function () {
        getJson('api/posts?where[secret_note][eq]=note Jan')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('where.secret_note');

        getJson('api/posts?where[nope][eq]=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('where.nope');
    });

    it('rejects invalid operators and values', function () {
        getJson('api/posts?where[views][contains]=1')->assertUnprocessable()->assertJsonValidationErrors('where.views');
        getJson('api/posts?where[views][between]=1')->assertUnprocessable()->assertJsonValidationErrors('where.views');
        getJson('api/posts?where[views]=1')->assertUnprocessable()->assertJsonValidationErrors('where.views');
        getJson('api/posts?where[views][eq][]=1')->assertUnprocessable()->assertJsonValidationErrors('where.views');
    });

    it('works on model endpoints', function () {
        Secret::create(['name' => 'a']);
        Secret::create(['name' => 'b']);

        getJson('api/secrets?where[name][ne]=a')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['b']);
    });
});

describe('aggregates', function () {
    it('computes an aggregate of the whole query', function () {
        getJson('api/posts?aggregate=count:*')->assertExactJson(['data' => [['aggregate' => 4]]]);
        getJson('api/posts?aggregate=sum:views')->assertExactJson(['data' => [['aggregate' => 190]]]);
        getJson('api/posts?aggregate=avg:views')->assertExactJson(['data' => [['aggregate' => 47.5]]]);
        getJson('api/posts?aggregate=max:views')->assertExactJson(['data' => [['aggregate' => 100]]]);
        getJson('api/posts?aggregate=min:created_at')->assertExactJson(['data' => [['aggregate' => '2025-01-15 09:30:00']]]);
        getJson('api/posts?aggregate=count:body')->assertExactJson(['data' => [['aggregate' => 2]]]);
    });

    it('returns 0 for counts and null otherwise when there are no rows', function () {
        getJson('api/posts?aggregate=count:*&where[views][gt]=1000')->assertExactJson(['data' => [['aggregate' => 0]]]);
        getJson('api/posts?aggregate=avg:views&where[views][gt]=1000')->assertExactJson(['data' => [['aggregate' => null]]]);
    });

    it('groups by date buckets like laravel-trend', function () {
        getJson('api/posts?aggregate=count:*&group=month:created_at')->assertExactJson(['data' => [
            ['group' => '2025-01', 'aggregate' => 2],
            ['group' => '2025-03', 'aggregate' => 1],
            ['group' => '2026-02', 'aggregate' => 1],
        ]]);

        getJson('api/posts?aggregate=sum:views&group=year:created_at')->assertExactJson(['data' => [
            ['group' => '2025', 'aggregate' => 90],
            ['group' => '2026', 'aggregate' => 100],
        ]]);

        getJson('api/posts?aggregate=count:*&group=day:created_at&where[created_at][between]=2025-01-01 00:00:00,2025-01-31 23:59:59')
            ->assertExactJson(['data' => [
                ['group' => '2025-01-15', 'aggregate' => 1],
                ['group' => '2025-01-20', 'aggregate' => 1],
            ]]);

        getJson('api/posts?aggregate=count:*&group=hour:created_at&where[views][eq]=10')
            ->assertExactJson(['data' => [['group' => '2025-01-15 09:00', 'aggregate' => 1]]]);

        getJson('api/posts?aggregate=count:*&group=minute:created_at&where[views][eq]=10')
            ->assertExactJson(['data' => [['group' => '2025-01-15 09:30:00', 'aggregate' => 1]]]);
    });

    it('groups by value', function () {
        getJson('api/posts?aggregate=avg:views&group=value:status')->assertExactJson(['data' => [
            ['group' => 'draft', 'aggregate' => 10],
            ['group' => 'locked', 'aggregate' => 100],
            ['group' => 'published', 'aggregate' => 40],
        ]]);
    });

    it('applies the table query, filters and search, and ignores pagination', function () {
        getJson('api/posts?aggregate=count:*&tab=drafts')->assertExactJson(['data' => [['aggregate' => 1]]]);
        getJson('api/posts?aggregate=count:*&search=Jan&per_page=1&page=3')->assertExactJson(['data' => [['aggregate' => 2]]]);
        getJson('api/posts?aggregate=count:*&filter[status]=published')->assertExactJson(['data' => [['aggregate' => 2]]]);
    });

    it('rejects invalid aggregates', function () {
        getJson('api/posts?aggregate=median:views')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
        getJson('api/posts?aggregate=sum')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
        getJson('api/posts?aggregate=sum:*')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
        getJson('api/posts?aggregate=sum:title')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
        getJson('api/posts?aggregate=max:secret_note')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
        getJson('api/posts?aggregate=count:*&group=week:created_at')->assertUnprocessable()->assertJsonValidationErrors('group');
        getJson('api/posts?aggregate=count:*&group=value:secret_note')->assertUnprocessable()->assertJsonValidationErrors('group');
        getJson('api/posts?group=month:created_at')->assertUnprocessable()->assertJsonValidationErrors('group');
    });

    it('works on relation manager and model endpoints', function () {
        Comment::create(['post_id' => $this->jan->id, 'body' => 'One']);
        Comment::create(['post_id' => $this->jan->id, 'body' => 'Two']);
        Comment::create(['post_id' => $this->mar->id, 'body' => 'Elsewhere']);

        getJson("api/posts/{$this->jan->id}/comments?aggregate=count:*")->assertExactJson(['data' => [['aggregate' => 2]]]);

        Secret::create(['name' => 'a']);

        getJson('api/secrets?aggregate=count:*&where[name][eq]=a')->assertExactJson(['data' => [['aggregate' => 1]]]);
    });

    it('stays inside the tenant', function () {
        $team = Team::create(['name' => 'Ours']);
        $this->user->teams()->attach($team);

        $team->posts()->create(['title' => 'Team post', 'views' => 5]);

        getJson("api/app/{$team->id}/posts?aggregate=sum:views")->assertExactJson(['data' => [['aggregate' => 5]]]);
    });
});

it('is off unless the plugin enables it', function () {
    FilamentApi::getPlugin(Filament::getPanel('admin'))->operatorFilters(false)->aggregates(false);

    getJson('api/posts?where[views][gt]=1')->assertUnprocessable()->assertJsonValidationErrors('where');
    getJson('api/posts?aggregate=count:*')->assertUnprocessable()->assertJsonValidationErrors('aggregate');
});
