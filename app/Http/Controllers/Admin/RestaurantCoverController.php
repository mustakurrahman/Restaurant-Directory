<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCoverRequest;
use App\Models\Restaurant;
use App\Support\PublicImage;

class RestaurantCoverController extends Controller
{
    public function store(UploadCoverRequest $request, Restaurant $restaurant)
    {
        $oldPath = $restaurant->cover_image;

        // store() picks a random file name, so the uploader's own file name is never used
        $path = $request->file('cover')->store(PublicImage::folderFor($restaurant->id), 'public');

        $restaurant->update(['cover_image' => $path]);
        PublicImage::delete($oldPath); // replaced: remove the previous file

        return $this->backToPhotos($restaurant, 'Cover photo saved.');
    }

    public function destroy(Restaurant $restaurant)
    {
        PublicImage::delete($restaurant->cover_image);
        $restaurant->update(['cover_image' => null]);

        return $this->backToPhotos($restaurant, 'Cover photo removed.');
    }

    // #photos scrolls the page down to the photos section
    private function backToPhotos(Restaurant $restaurant, string $message)
    {
        return redirect(route('admin.restaurants.edit', $restaurant).'#photos')->with('status', $message);
    }
}
