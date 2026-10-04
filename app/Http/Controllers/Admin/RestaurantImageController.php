<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateImageRequest;
use App\Http\Requests\UploadImagesRequest;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use App\Support\PublicImage;

class RestaurantImageController extends Controller
{
    public function store(UploadImagesRequest $request, Restaurant $restaurant)
    {
        // reorder() clears the relation's built-in ordering, which MySQL rejects together with max()
        $position = (int) $restaurant->images()->reorder()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $restaurant->images()->create([
                'path' => $file->store(PublicImage::folderFor($restaurant->id), 'public'),
                'alt_text' => $restaurant->name, // a sensible default the admin can improve
                'sort_order' => ++$position,     // new photos go to the end
            ]);
        }

        $count = count($request->file('images'));

        return $this->backToPhotos($restaurant, $count === 1 ? 'Photo added.' : "{$count} photos added.");
    }

    public function update(UpdateImageRequest $request, Restaurant $restaurant, RestaurantImage $image)
    {
        $image->update($request->validated());

        return $this->backToPhotos($restaurant, 'Photo description saved.');
    }

    public function destroy(Restaurant $restaurant, RestaurantImage $image)
    {
        $image->delete(); // the model removes the file from disk

        return $this->backToPhotos($restaurant, 'Photo deleted.');
    }

    private function backToPhotos(Restaurant $restaurant, string $message)
    {
        return redirect(route('admin.restaurants.edit', $restaurant).'#photos')->with('status', $message);
    }
}
