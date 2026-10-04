<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The admin pages for anything that has a name and a slug: cities, cuisines and amenities.
 * The list (with search and paging), create, edit and delete are written once here. Each real controller only
 * says WHICH model, request, wording and extra fields it uses (see CityController).
 *
 * A record that restaurants still use cannot be deleted: the admin gets a friendly message naming some of them.
 */
abstract class NameSlugController extends Controller
{
    private const PER_PAGE = 15;

    /** @return class-string<Model> e.g. City::class (the model needs withCount('restaurants') and search()) */
    abstract protected function model(): string;

    /** @return class-string<\Illuminate\Foundation\Http\FormRequest> the Form Request that validates the form */
    abstract protected function requestClass(): string;

    /** Singular word shown to the admin, e.g. "city" */
    abstract protected function noun(): string;

    /** Plural word, also the route name, e.g. "cities" (routes are admin.cities.*) */
    abstract protected function plural(): string;

    /** What the admin should do to be able to delete a record that is still in use */
    abstract protected function howToFreeIt(): string;

    /** Extra fields beyond name and slug: 'description' and/or 'icon' */
    protected function features(): array
    {
        return [];
    }

    public function index(Request $request)
    {
        $items = $this->model()::query()
            ->withCount('restaurants') // adds a restaurants_count column in one query (no N+1)
            ->search($this->searchTerm($request))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString(); // keeps the search word when clicking page 2

        // The last item of the last page was deleted: step back to the last page that still exists
        if ($items->isEmpty() && $items->currentPage() > 1) {
            return redirect($items->url($items->lastPage()));
        }

        return $this->page('index', ['items' => $items, 'search' => $request->query('q')]);
    }

    public function create()
    {
        return $this->page('create', ['item' => null]);
    }

    public function store()
    {
        $this->model()::create(app($this->requestClass())->validated()); // resolving the Form Request validates it

        return $this->backToList()->with('status', ucfirst($this->noun()).' created.');
    }

    public function edit(string $id)
    {
        return $this->page('edit', ['item' => $this->find($id)]);
    }

    public function update(string $id)
    {
        $item = $this->find($id);
        $item->update(app($this->requestClass())->validated());

        return $this->backToList()->with('status', ucfirst($this->noun()).' updated.');
    }

    public function destroy(string $id)
    {
        $item = $this->find($id);

        // Deleting something restaurants still use would leave them without it (or, for a city, without a home)
        $using = $item->restaurants()->count();

        if ($using > 0) {
            return back()->with('error', $this->blockedMessage($item, $using));
        }

        $item->delete();

        return $this->backToList()->with('status', ucfirst($this->noun()).' deleted.');
    }

    // ---------- helpers ----------

    private function find(string $id): Model
    {
        return $this->model()::findOrFail($id);
    }

    private function backToList()
    {
        return to_route('admin.'.$this->plural().'.index');
    }

    private function page(string $view, array $data)
    {
        return view("admin.name-slug.{$view}", $data + [
            'noun' => $this->noun(),
            'plural' => $this->plural(),
            'routePrefix' => 'admin.'.$this->plural(),
            'features' => $this->features(),
        ]);
    }

    /** "Italian" can't be deleted: 3 restaurants still use it (Trattoria, Pasta House, Luigi's). Untick it ... */
    private function blockedMessage(Model $item, int $using): string
    {
        $examples = $item->restaurants()->orderBy('name')->limit(3)->pluck('name');
        $more = $using - $examples->count();

        return "“{$item->name}” can’t be deleted because {$using} ".($using === 1 ? 'restaurant still uses' : 'restaurants still use')
            .' it ('.$examples->join(', ').($more > 0 ? " and {$more} more" : '').'). '.$this->howToFreeIt();
    }
}
