<?php

namespace Tests\Feature\Admin;

use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class RestaurantPhotoTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
        Storage::fake('public'); // uploads go to a temporary disk that is wiped after each test

        $this->restaurant = Restaurant::factory()->create(['name' => 'Photo Place', 'cover_image' => null]);
    }

    private function coverUrl(): string
    {
        return route('admin.restaurants.cover.store', $this->restaurant);
    }

    private function imagesUrl(): string
    {
        return route('admin.restaurants.images.store', $this->restaurant);
    }

    /** Adds a real file to the fake disk inside this restaurant's folder and returns its path */
    private function storedFile(string $name = 'existing.jpg'): string
    {
        $path = "restaurants/{$this->restaurant->id}/{$name}";
        Storage::disk('public')->put($path, 'fake image bytes');

        return $path;
    }

    // ---------- Cover ----------

    public function test_cover_upload_saves_the_file_in_the_restaurants_folder(): void
    {
        $this->post($this->coverUrl(), ['cover' => UploadedFile::fake()->image('front.jpg', 800, 600)])
            ->assertRedirect(route('admin.restaurants.edit', $this->restaurant).'#photos')
            ->assertSessionHas('status', 'Cover photo saved.');

        $path = $this->restaurant->fresh()->cover_image;
        $this->assertStringStartsWith("restaurants/{$this->restaurant->id}/", $path);
        $this->assertStringNotContainsString('front', $path); // the uploader's file name is never used
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_cover_deletes_the_old_file(): void
    {
        $old = $this->storedFile('old.jpg');
        $this->restaurant->update(['cover_image' => $old]);

        $this->post($this->coverUrl(), ['cover' => UploadedFile::fake()->image('new.png')]);

        $new = $this->restaurant->fresh()->cover_image;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_removing_the_cover_deletes_the_file_and_clears_the_column(): void
    {
        $path = $this->storedFile();
        $this->restaurant->update(['cover_image' => $path]);

        $this->delete(route('admin.restaurants.cover.destroy', $this->restaurant))
            ->assertSessionHas('status', 'Cover photo removed.');

        $this->assertNull($this->restaurant->fresh()->cover_image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cover_accepts_jpg_png_and_webp(): void
    {
        foreach (['a.jpg', 'b.jpeg', 'c.png', 'd.webp'] as $name) {
            $this->post($this->coverUrl(), ['cover' => UploadedFile::fake()->image($name)])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_cover_rejects_wrong_or_fake_or_oversized_files(): void
    {
        $bad = [
            'gif' => UploadedFile::fake()->image('anim.gif'),
            'pdf' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
            'svg' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            'text renamed to .jpg' => UploadedFile::fake()->create('evil.jpg', 10, 'text/plain'),
            'php script' => UploadedFile::fake()->create('shell.php', 10),
            'over 3 MB' => UploadedFile::fake()->image('huge.jpg')->size(3073),
        ];

        foreach ($bad as $case => $file) {
            $this->flushSession(); // so errors left over from the previous case cannot fake a pass

            $response = $this->post($this->coverUrl(), ['cover' => $file]);

            try {
                $response->assertSessionHasErrors('cover');
            } catch (AssertionFailedError) {
                $this->fail("This file was wrongly accepted: {$case}");
            }
        }

        $this->assertNull($this->restaurant->fresh()->cover_image);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_cover_of_exactly_3_mb_is_accepted(): void
    {
        $this->post($this->coverUrl(), ['cover' => UploadedFile::fake()->image('limit.jpg')->size(3072)])
            ->assertSessionHasNoErrors();
    }

    public function test_cover_is_required(): void
    {
        $this->post($this->coverUrl(), [])->assertSessionHasErrors(['cover' => 'Choose a photo first.']);
    }

    // ---------- Gallery ----------

    public function test_gallery_upload_adds_several_photos_in_order_with_default_alt_text(): void
    {
        RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'sort_order' => 5]);

        $this->post($this->imagesUrl(), ['images' => [
            UploadedFile::fake()->image('1.jpg'),
            UploadedFile::fake()->image('2.png'),
            UploadedFile::fake()->image('3.webp'),
        ]])->assertSessionHas('status', '3 photos added.');

        $images = $this->restaurant->images()->get();
        $this->assertCount(4, $images);
        $this->assertSame([5, 6, 7, 8], $images->pluck('sort_order')->all()); // new photos go to the end

        $added = $images->slice(1);
        foreach ($added as $image) {
            $this->assertSame('Photo Place', $image->alt_text);
            $this->assertStringStartsWith("restaurants/{$this->restaurant->id}/", $image->path);
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_one_bad_file_rejects_the_whole_gallery_upload(): void
    {
        $this->post($this->imagesUrl(), ['images' => [
            UploadedFile::fake()->image('good.jpg'),
            UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'),
        ]])->assertSessionHasErrors('images.1');

        $this->assertDatabaseCount('restaurant_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_gallery_upload_limits_and_requirements(): void
    {
        $eleven = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 11));
        $this->post($this->imagesUrl(), ['images' => $eleven])->assertSessionHasErrors('images');

        $ten = array_map(fn ($i) => UploadedFile::fake()->image("q{$i}.jpg"), range(1, 10));
        $this->post($this->imagesUrl(), ['images' => $ten])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('restaurant_images', 10);

        $this->post($this->imagesUrl(), [])->assertSessionHasErrors('images');
    }

    public function test_alt_text_can_be_edited_and_cleared_but_not_too_long(): void
    {
        $image = RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'alt_text' => 'Old']);
        $url = route('admin.restaurants.images.update', [$this->restaurant, $image]);

        $this->patch($url, ['alt_text' => 'Candlelit dining room'])->assertSessionHas('status');
        $this->assertSame('Candlelit dining room', $image->fresh()->alt_text);

        $this->patch($url, ['alt_text' => ''])->assertSessionHasNoErrors();
        $this->assertNull($image->fresh()->alt_text);

        $this->patch($url, ['alt_text' => str_repeat('x', 256)])->assertSessionHasErrors('alt_text');
    }

    public function test_deleting_a_gallery_photo_removes_row_and_file(): void
    {
        $path = $this->storedFile('gallery.jpg');
        $image = RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'path' => $path]);

        $this->delete(route('admin.restaurants.images.destroy', [$this->restaurant, $image]))
            ->assertSessionHas('status', 'Photo deleted.');

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_photo_cannot_be_changed_through_another_restaurants_url(): void
    {
        $other = Restaurant::factory()->create();
        $image = RestaurantImage::factory()->create(['restaurant_id' => $other->id]);

        $this->delete(route('admin.restaurants.images.destroy', [$this->restaurant, $image]))->assertNotFound();
        $this->patch(route('admin.restaurants.images.update', [$this->restaurant, $image]), ['alt_text' => 'Hijack'])->assertNotFound();

        $this->assertModelExists($image);
    }

    // ---------- Cleanup rules ----------

    public function test_deleting_a_restaurant_removes_its_photo_folder_but_not_other_restaurants(): void
    {
        $cover = $this->storedFile('cover.jpg');
        $gallery = $this->storedFile('gallery.jpg');
        $this->restaurant->update(['cover_image' => $cover]);
        RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'path' => $gallery]);

        $other = Restaurant::factory()->create();
        $othersFile = "restaurants/{$other->id}/keep.jpg";
        Storage::disk('public')->put($othersFile, 'bytes');

        $this->delete(route('admin.restaurants.destroy', $this->restaurant))->assertSessionHas('status');

        Storage::disk('public')->assertMissing([$cover, $gallery]);
        Storage::disk('public')->assertExists($othersFile);
    }

    public function test_files_outside_the_restaurants_folder_are_never_deleted(): void
    {
        // e.g. a shared placeholder: removing the database row must not remove this file
        Storage::disk('public')->put('placeholders/shared.jpg', 'bytes');
        $image = RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'path' => 'placeholders/shared.jpg']);
        $this->restaurant->update(['cover_image' => 'placeholders/shared.jpg']);

        $this->delete(route('admin.restaurants.images.destroy', [$this->restaurant, $image]));
        $this->delete(route('admin.restaurants.cover.destroy', $this->restaurant));

        Storage::disk('public')->assertExists('placeholders/shared.jpg');
    }

    // ---------- What the admin sees ----------

    public function test_edit_page_shows_uploaded_photos(): void
    {
        $cover = $this->storedFile('cover.jpg');
        $gallery = $this->storedFile('gallery.jpg');
        $this->restaurant->update(['cover_image' => $cover]);
        RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'path' => $gallery, 'alt_text' => 'Terrace view']);

        $this->get(route('admin.restaurants.edit', $this->restaurant))
            ->assertOk()
            ->assertSee('/storage/'.$cover, false)
            ->assertSee('/storage/'.$gallery, false)
            ->assertSee('value="Terrace view"', false)
            ->assertSee('Replace cover photo')
            ->assertSee('Remove cover')
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_edit_page_explains_sample_data_placeholders_instead_of_broken_images(): void
    {
        $this->restaurant->update(['cover_image' => 'placeholders/restaurant-3.jpg']);
        RestaurantImage::factory()->create(['restaurant_id' => $this->restaurant->id, 'path' => 'placeholders/gallery-1.jpg']);

        $this->get(route('admin.restaurants.edit', $this->restaurant))
            ->assertOk()
            ->assertSee('is sample data and has no file yet')
            ->assertSee('Sample data: no file yet')
            ->assertDontSee('/storage/placeholders', false);
    }

    public function test_create_page_tells_the_admin_photos_come_after_saving(): void
    {
        $this->get(route('admin.restaurants.create'))
            ->assertOk()
            ->assertSee('Photos are added after saving')
            ->assertDontSee('Upload cover');
    }

    public function test_failed_photo_upload_does_not_untick_the_restaurants_categories(): void
    {
        $cuisine = Cuisine::factory()->create();
        $this->restaurant->cuisines()->attach($cuisine);

        $response = $this->from(route('admin.restaurants.edit', $this->restaurant))
            ->followingRedirects()
            ->post($this->coverUrl(), ['cover' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')]);

        $html = $response->getContent();
        $this->assertStringContainsString('Photos must be JPG, PNG or WebP files.', $html);
        // The cuisine must still be ticked, or the next "Save restaurant" click would wipe it
        $this->assertMatchesRegularExpression('/name="cuisines\[\]" value="'.$cuisine->id.'"\s+checked/', $html);
    }
}
