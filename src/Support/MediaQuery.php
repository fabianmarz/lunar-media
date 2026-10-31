<?php

namespace LunarMedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The filtered media listing shared by the Media index and the picker, so the
 * two never drift apart.
 */
class MediaQuery
{
    public function __construct(private readonly OwnerResolver $owners) {}

    /**
     * @return Builder<Media>
     */
    public function build(Request $request): Builder
    {
        $owner = $request->string('owner')->value();
        $collection = $request->string('collection')->value();

        return Media::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->string('q')->value()).'%';

                $query->where(fn (Builder $q) => $q
                    ->whereRaw("name like ? escape '!'", [$like])
                    ->orWhere('custom_properties->name', 'like', $like)
                    ->orWhereRaw("file_name like ? escape '!'", [$like]));
            })
            ->when(
                array_key_exists($owner, $this->owners->typeOptions()),
                fn (Builder $query) => $query->whereIn('model_type', $this->owners->modelTypes($owner)),
            )
            ->when($collection !== '', fn (Builder $query) => $query->where('collection_name', $collection))
            ->with(['model' => fn (MorphTo $morph) => $morph->morphWith($this->owners->eagerLoads())])
            ->latest('id');
    }

    /**
     * @return array{owners: array<string, string>, collections: Collection<int, string>}
     */
    public function filterOptions(): array
    {
        return [
            'owners' => $this->owners->typeOptions(),
            'collections' => Media::query()->distinct()->orderBy('collection_name')->pluck('collection_name'),
        ];
    }
}
