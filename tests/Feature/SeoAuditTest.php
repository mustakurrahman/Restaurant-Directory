<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SEO checklist, run against every public page at once. If a future change breaks a title, canonical address,
 * social tag or structured-data block on ANY page, this test names the page and the problem.
 */
class SeoAuditTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** A small but complete directory: several cities and cuisines, long names, reviews, hours, a draft */
    private function seedDirectory(): void
    {
        $chicago = City::factory()->create(['name' => 'Chicago']);
        $la = City::factory()->create(['name' => 'Los Angeles']);
        City::factory()->create(['name' => 'Ghost Town']); // no restaurants: must not appear anywhere
        $cuisines = collect(['Italian', 'Mediterranean', 'Thai'])->map(fn ($name) => Cuisine::factory()->create(['name' => $name]));

        foreach (range(1, 13) as $n) { // 13 in one city, so page 2 exists
            $restaurant = Restaurant::factory()->create(['city_id' => $chicago->id, 'name' => sprintf('Chicago Place %02d', $n)]);
            $restaurant->cuisines()->attach($cuisines[$n % 3]);
        }

        $long = Restaurant::factory()->create(['city_id' => $la->id, 'name' => 'The Golden Dragon Palace Restaurant']);
        $long->cuisines()->attach($cuisines[1]);
        Review::factory()->create(['restaurant_id' => $long->id, 'status' => 'approved', 'rating' => 5]);
        OpeningHour::create(['restaurant_id' => $long->id, 'day_of_week' => 1, 'opens_at' => '11:00', 'closes_at' => '22:00', 'is_closed' => false]);

        Restaurant::factory()->create(['status' => 'draft', 'name' => 'Secret Draft', 'city_id' => $la->id]);
    }

    private function sitemapUrls(): array
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        return array_map(fn ($entry) => (string) $entry->loc, iterator_to_array($xml->url, false));
    }

    /** Reads the tags of one page into an array we can check */
    private function parse(string $html): array
    {
        $one = fn (string $pattern) => preg_match($pattern, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;
        $count = fn (string $pattern) => preg_match_all($pattern, $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

        return [
            'title' => $one('#<title>(.*?)</title>#s'),
            'titles' => $count('#<title>#'),
            'description' => $one('#<meta name="description" content="([^"]*)"#'),
            'descriptions' => $count('#<meta name="description"#'),
            'canonical' => $one('#<link rel="canonical" href="([^"]*)"#'),
            'canonicals' => $count('#<link rel="canonical"#'),
            'robots' => $one('#<meta name="robots" content="([^"]*)"#'),
            'h1' => $count('#<h1[\s>]#'),
            'lang' => $count('#<html lang="en"#'),
            'viewport' => $count('#name="viewport"#'),
            'og' => collect(['og:title', 'og:description', 'og:url', 'og:type', 'og:image', 'og:site_name'])
                ->mapWithKeys(fn ($p) => [$p => $one('#property="'.preg_quote($p, '#').'" content="([^"]+)"#')])->all(),
            'twitter' => collect(['twitter:card', 'twitter:title', 'twitter:description'])
                ->mapWithKeys(fn ($n) => [$n => $one('#name="'.preg_quote($n, '#').'" content="([^"]+)"#')])->all(),
            'imagesWithoutAlt' => collect(preg_match_all('#<img\b[^>]*>#', $html, $imgs) ? $imgs[0] : [])->reject(fn ($tag) => str_contains($tag, ' alt='))->count(),
            'deadLinks' => $count('#href="(\#|)"#'),
            'jsonLd' => collect($blocks[1])->map(fn ($json) => json_decode($json, true)),
        ];
    }

    public function test_every_public_page_passes_the_seo_checklist(): void
    {
        $this->seedDirectory();

        $sitemap = $this->sitemapUrls();
        $special = [route('restaurants.index', ['page' => 2]), route('restaurants.index', ['city' => 'chicago']), route('restaurants.index', ['sort' => 'rating']), route('cities.show', ['city' => 'chicago', 'page' => 2])];

        $problems = [];
        $titles = [];
        $descriptions = [];

        foreach (array_merge($sitemap, $special) as $url) {
            $isSpecial = in_array($url, $special, true);
            $html = $this->get($url)->assertOk()->getContent();
            $page = $this->parse($html);
            $where = str_replace(config('app.url'), '', $url) ?: '/';
            $fail = function (string $message) use (&$problems, $where) {
                $problems[] = "{$where}: {$message}";
            };

            $page['lang'] === 1 || $fail('missing <html lang>');
            $page['viewport'] === 1 || $fail('missing viewport');

            // Title: exactly one, 10 to 70 characters, unique across the site
            $page['titles'] === 1 || $fail("title tag count {$page['titles']}");
            $length = mb_strlen((string) $page['title']);
            ($length >= 10 && $length <= 70) || $fail("title length {$length}: {$page['title']}");
            if (! $isSpecial) {
                isset($titles[$page['title']]) && $fail("duplicate title (also {$titles[$page['title']]})");
                $titles[$page['title']] = $where;
            }

            // Description: exactly one, 50 to 175 characters, unique across the site
            $page['descriptions'] === 1 || $fail("description count {$page['descriptions']}");
            $length = mb_strlen((string) $page['description']);
            ($length >= 50 && $length <= 175) || $fail("description length {$length}: {$page['description']}");
            if (! $isSpecial) {
                isset($descriptions[$page['description']]) && $fail("duplicate description (also {$descriptions[$page['description']]})");
                $descriptions[$page['description']] = $where;
            }

            // Canonical: exactly one, absolute, no #fragment; pages in the sitemap point to themselves
            $page['canonicals'] === 1 || $fail("canonical count {$page['canonicals']}");
            preg_match('#^https?://[^\#]+$#', (string) $page['canonical']) || $fail("bad canonical {$page['canonical']}");
            $isSpecial || $page['canonical'] === $url || $fail("canonical {$page['canonical']} is not the page's own address");

            // Search engine rules: pages in the sitemap are indexable; filtered or re-sorted views are not
            $isSpecial || str_starts_with((string) $page['robots'], 'index') || $fail("sitemap page not indexable: {$page['robots']}");
            $url !== route('restaurants.index', ['city' => 'chicago']) || str_starts_with((string) $page['robots'], 'noindex') || $fail('filtered view must be noindex');
            $url !== route('restaurants.index', ['sort' => 'rating']) || str_starts_with((string) $page['robots'], 'noindex') || $fail('re-sorted view must be noindex');

            // Social sharing tags
            foreach ($page['og'] + $page['twitter'] as $tag => $value) {
                filled($value) || $fail("missing {$tag}");
            }
            $page['og']['og:url'] === $page['canonical'] || $fail('og:url differs from canonical');
            preg_match('#^https?://#', (string) $page['og']['og:image']) || $fail('og:image is not an absolute address');

            // Structure and accessibility
            $page['h1'] === 1 || $fail("h1 count {$page['h1']}");
            $page['imagesWithoutAlt'] === 0 || $fail('image without alt text');
            $page['deadLinks'] === 0 || $fail('dead link (href="#" or empty)');

            // Structured data: every block parses, and each page type carries the right kinds
            $types = $page['jsonLd']->map(fn ($block) => $block['@type'] ?? null)->all();
            $page['jsonLd']->contains(null) && $fail('a JSON-LD block does not parse');
            $expected = match (true) {
                str_contains($where, '/restaurant/') => ['Restaurant', 'BreadcrumbList'],
                $where === '/' => ['WebSite'],
                default => ['BreadcrumbList'],
            };
            foreach ($expected as $type) {
                in_array($type, $types, true) || $fail("missing JSON-LD {$type}");
            }
        }

        $this->assertSame([], $problems, "SEO problems found:\n".implode("\n", $problems));
    }

    public function test_the_checklist_really_covers_the_whole_directory(): void
    {
        $this->seedDirectory();

        $sitemap = $this->sitemapUrls();

        $this->assertGreaterThanOrEqual(14 + 3 + 2 + 6, count($sitemap)); // 14 restaurants, 2 cities, 3 cuisines, 6 fixed
        $this->assertContains(route('restaurants.show', Restaurant::where('name', 'Chicago Place 01')->first()), $sitemap);
    }

    // ---------- Titles of restaurant pages ----------

    public function test_a_long_restaurant_name_drops_the_cuisine_then_the_city_to_keep_the_title_short(): void
    {
        // The site name adds 31 characters, so about 39 are left for "Name – Cuisine in City"
        $city = City::factory()->create(['name' => 'Los Angeles']);
        $short = City::factory()->create(['name' => 'Chicago']);
        $cuisine = Cuisine::factory()->create(['name' => 'Mediterranean']);
        $thai = Cuisine::factory()->create(['name' => 'Thai']);

        $fits = Restaurant::factory()->create(['city_id' => $short->id, 'name' => 'Olive Table']);
        $fits->cuisines()->attach($thai);
        $medium = Restaurant::factory()->create(['city_id' => $city->id, 'name' => 'Golden Dragon Grill']);
        $medium->cuisines()->attach($cuisine);
        $long = Restaurant::factory()->create(['city_id' => $city->id, 'name' => str_repeat('Extraordinarily ', 3).'Long Name']);
        $long->cuisines()->attach($cuisine);

        $title = fn (Restaurant $restaurant) => $this->parse($this->get(route('restaurants.show', $restaurant))->getContent())['title'];
        $suffix = ' | '.config('app.name');

        $this->assertSame('Olive Table – Thai in Chicago'.$suffix, $title($fits));                      // everything fits
        $this->assertSame('Golden Dragon Grill in Los Angeles'.$suffix, $title($medium));              // cuisine dropped
        $this->assertSame($long->name.$suffix, $title($long));                                          // only the name is left
        $this->assertLessThanOrEqual(70, mb_strlen($title($fits)));
        $this->assertLessThanOrEqual(70, mb_strlen($title($medium)));
    }

    public function test_a_title_written_by_the_owner_is_never_shortened(): void
    {
        $restaurant = Restaurant::factory()->create(['meta_title' => 'My very own long title that the owner chose on purpose, word for word']);

        $this->assertStringContainsString('My very own long title that the owner chose on purpose, word for word', $this->parse($this->get(route('restaurants.show', $restaurant))->getContent())['title']);
    }

    // ---------- Pages that must stay out of Google ----------

    public function test_error_pages_and_forms_results_are_not_indexable(): void
    {
        $this->assertStringStartsWith('noindex', $this->parse($this->get('/no-such-page')->getContent())['robots']);

        $this->seedDirectory();
        $this->assertStringStartsWith('noindex', $this->parse($this->get(route('restaurants.index', ['q' => 'place']))->getContent())['robots']);
    }

    public function test_the_admin_is_never_indexable(): void
    {
        foreach (['/admin', '/admin/cities', '/admin/reviews', '/admin/restaurants'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, $path);
        }
    }
}
