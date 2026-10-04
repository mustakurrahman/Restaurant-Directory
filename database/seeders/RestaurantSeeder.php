<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestaurantSeeder extends Seeder
{
    // Rough city centres; each restaurant gets a small random offset so map pins spread out
    private const CENTERS = [
        'New York' => [40.7128, -74.0060],
        'Los Angeles' => [34.0522, -118.2437],
        'Chicago' => [41.8781, -87.6298],
        'London' => [51.5074, -0.1278],
        'Dubai' => [25.2048, 55.2708],
    ];

    // One selling point per cuisine, used to write believable descriptions
    private const BLURBS = [
        'Italian' => 'Handmade pasta, wood-fired pizza and a short, carefully chosen wine list.',
        'Indian' => 'Slow-cooked curries, fresh tandoor breads and spice blends ground in-house.',
        'Japanese' => 'Hand-cut sushi, rich ramen broths and seasonal small plates.',
        'Mexican' => 'Street-style tacos, house-made salsas and tortillas pressed to order.',
        'Chinese' => 'Regional classics, hand-pulled noodles and dim sum served fresh all day.',
        'French' => 'Classic bistro cooking, buttery pastries and a thoughtful cheese selection.',
        'American' => 'Smoked meats, flame-grilled burgers and comforting sides done properly.',
        'Thai' => 'Fragrant curries, bright herb-driven salads and wok-fired street food.',
        'Mediterranean' => 'Sharing platters, grilled meats and olive-oil-rich vegetable dishes.',
        'Seafood' => 'Daily-caught fish, a raw bar and plenty of shellfish on ice.',
    ];

    public function run(): void
    {
        $cities = City::pluck('id', 'name');
        $cuisines = Cuisine::pluck('id', 'name');
        $amenityIds = Amenity::pluck('id')->all();

        foreach ($this->restaurants() as $i => [$name, $city, $cuisineNames, $price, $flag]) {
            [$lat, $lng] = self::CENTERS[$city];
            $slug = Str::slug($name);

            // firstOrCreate: running the seeder twice never creates duplicates
            $restaurant = Restaurant::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $this->describe($name, $city, $cuisineNames),
                'address' => fake()->streetAddress().', '.$city,
                'city_id' => $cities[$city],
                // 555-01xx numbers and example.com are reserved for fiction/examples
                'phone' => fake()->numerify('(###) 555-01##'),
                'email' => "hello@{$slug}.example.com",
                'website' => "https://{$slug}.example.com",
                'price_range' => $price,
                'latitude' => round($lat + fake()->randomFloat(4, -0.04, 0.04), 7),
                'longitude' => round($lng + fake()->randomFloat(4, -0.04, 0.04), 7),
                'cover_image' => 'placeholders/restaurant-'.(($i % 8) + 1).'.jpg',
                'is_featured' => $flag === 'featured',
                'status' => $flag === 'draft' ? 'draft' : 'published',
            ]);

            // Already seeded on an earlier run: leave its related rows alone
            if (! $restaurant->wasRecentlyCreated) {
                continue;
            }

            $restaurant->cuisines()->attach(
                collect($cuisineNames)->map(fn ($n) => $cuisines[$n])->all()
            );
            $restaurant->amenities()->attach(
                fake()->randomElements($amenityIds, fake()->numberBetween(3, 7))
            );

            $this->seedImages($restaurant, $i);
            $this->seedHours($restaurant);
        }

        $this->seedReviews();
    }

    // Placeholder paths only; real uploads replace these in Sprint 3
    private function seedImages(Restaurant $restaurant, int $i): void
    {
        $captions = ['Dining area', 'Signature dish', 'Bar and drinks'];

        foreach ($captions as $n => $caption) {
            $restaurant->images()->create([
                'path' => 'placeholders/gallery-'.((($i + $n) % 6) + 1).'.jpg',
                'alt_text' => "{$caption} at {$restaurant->name}",
                'sort_order' => $n + 1,
            ]);
        }
    }

    private function seedHours(Restaurant $restaurant): void
    {
        $opens = fake()->randomElement(['10:00', '11:00', '12:00']);
        // About 1 in 4 restaurants closes all day on Monday (1) or Sunday (7)
        $closedDay = fake()->boolean(25) ? fake()->randomElement([1, 7]) : null;

        foreach (range(1, 7) as $day) {
            $hours = OpeningHour::factory()->for($restaurant);

            if ($day === $closedDay) {
                $hours = $hours->closed();
            } else {
                // Open later on Friday (5) and Saturday (6)
                $hours = $hours->state([
                    'opens_at' => $opens,
                    'closes_at' => in_array($day, [5, 6]) ? '23:30' : '22:00',
                ]);
            }

            $hours->create(['day_of_week' => $day]);
        }
    }

    // Three approved reviews on 12 random published restaurants
    private function seedReviews(): void
    {
        if (Review::exists()) {
            return; // already seeded
        }

        Restaurant::published()->inRandomOrder()->limit(12)->get()->each(
            fn (Restaurant $r) => Review::factory()->count(3)->approved()->for($r)->create()
        );
    }

    private function describe(string $name, string $city, array $cuisineNames): string
    {
        $kinds = implode(' and ', $cuisineNames);
        $blurb = self::BLURBS[$cuisineNames[0]];
        $occasion = fake()->randomElement([
            'date night', 'family dinners', 'lunch with friends', 'a quick bite after work', 'celebrating with a group',
        ]);
        $vibe = fake()->randomElement([
            'The room is warm and lively, with attentive staff who know the menu well.',
            'Expect a relaxed, welcoming atmosphere and generous portions.',
            'The dining room is stylish but unfussy, and the kitchen is open to view.',
        ]);

        return "{$name} brings {$kinds} cooking to {$city}. {$blurb}\n\n{$vibe} A great choice for {$occasion}.";
    }

    /**
     * Fictional restaurants: [name, city, cuisines, price 1-4, flag]
     * flag: 'featured' shows on the homepage, 'draft' stays hidden from the public.
     */
    private function restaurants(): array
    {
        return [
            // New York
            ['Trattoria Bella Luna', 'New York', ['Italian'], 3, 'featured'],
            ['Spice Route Kitchen', 'New York', ['Indian'], 2, ''],
            ['Sakura Table', 'New York', ['Japanese'], 3, ''],
            ['Brooklyn Smokehouse', 'New York', ['American'], 2, ''],
            ['Le Petit Bistro', 'New York', ['French'], 4, 'featured'],
            ['Harbor & Hook', 'New York', ['Seafood'], 3, ''],
            // Los Angeles
            ['Casa Luz', 'Los Angeles', ['Mexican'], 2, 'featured'],
            ['Golden Dragon Palace', 'Los Angeles', ['Chinese'], 2, ''],
            ['Sunset Grill', 'Los Angeles', ['American'], 3, ''],
            ['Siam Garden', 'Los Angeles', ['Thai'], 2, ''],
            ['Olive & Vine', 'Los Angeles', ['Mediterranean'], 3, ''],
            ['Pacific Catch', 'Los Angeles', ['Seafood', 'Japanese'], 3, 'draft'],
            // Chicago
            ['Deep Dish Society', 'Chicago', ['Italian', 'American'], 2, ''],
            ['Maison Lumiere', 'Chicago', ['French'], 4, 'featured'],
            ['Saffron & Cardamom', 'Chicago', ['Indian'], 2, ''],
            ['El Rincon Taqueria', 'Chicago', ['Mexican'], 1, ''],
            ['Lakeside Oyster House', 'Chicago', ['Seafood'], 3, ''],
            ['Bangkok Street Kitchen', 'Chicago', ['Thai'], 1, ''],
            // London
            ['The Gilded Spoon', 'London', ['French', 'Mediterranean'], 4, 'featured'],
            ['Curry Lane', 'London', ['Indian'], 2, ''],
            ['Tokyo Ramen House', 'London', ['Japanese'], 2, ''],
            ['Borough Pizza Co', 'London', ['Italian'], 2, ''],
            ['The Thames Fish Bar', 'London', ['Seafood'], 2, ''],
            ['Dragon Gate Dim Sum', 'London', ['Chinese'], 3, ''],
            // Dubai
            ['Al Noor Mezze', 'Dubai', ['Mediterranean'], 3, 'featured'],
            ['Marina Sushi Lounge', 'Dubai', ['Japanese'], 4, ''],
            ['Desert Rose Grill', 'Dubai', ['American'], 3, ''],
            ['Spice Souk', 'Dubai', ['Indian'], 2, ''],
            ['Palm Bay Seafood', 'Dubai', ['Seafood'], 4, ''],
            ['Casa del Mar', 'Dubai', ['Mexican'], 2, 'draft'],
        ];
    }
}
