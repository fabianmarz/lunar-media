<?php

namespace LunarMedia\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Core\Contracts\Actions\Media\AddsMedia;
use Lunar\Core\Contracts\Actions\Media\UpdatesMedia;
use Lunar\Core\Models\Asset;
use LunarMedia\Http\Requests\MediaUpdateRequest;
use LunarMedia\Http\Requests\MediaUploadRequest;
use LunarMedia\Support\OwnerResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController
{
    public function index(Request $request, OwnerResolver $owners): Response
    {
        $owner = $request->string('owner')->value();
        $collection = $request->string('collection')->value();

        $media = Media::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->string('q')->value()).'%';

                $query->where(fn (Builder $q) => $q
                    ->whereRaw("name like ? escape '!'", [$like])
                    ->orWhere('custom_properties->name', 'like', $like)
                    ->orWhereRaw("file_name like ? escape '!'", [$like]));
            })
            ->when(
                array_key_exists($owner, $owners->typeOptions()),
                fn (Builder $query) => $query->whereIn('model_type', $owners->modelTypes($owner)),
            )
            ->when($collection !== '', fn (Builder $query) => $query->where('collection_name', $collection))
            ->with(['model' => fn (MorphTo $morph) => $morph->morphWith($owners->eagerLoads())])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $media->setCollection($media->getCollection()->map(fn (Media $item): array => [
            'id' => $item->id,
            'name' => $this->displayName($item),
            'file_name' => $item->file_name,
            'mime_type' => $item->mime_type,
            'size' => Number::fileSize($item->size),
            'collection_name' => $item->collection_name,
            'created_at' => $item->created_at?->toDateTimeString(),
            'thumbnail' => str_starts_with((string) $item->mime_type, 'image/')
                ? $item->getAvailableUrl(['small'])
                : null,
            'owner' => $owners->describe($item->model, $item->model_type),
        ]));

        return Inertia::render('media::Media/Index', [
            'media' => $media,
            'columns' => [
                ['key' => 'file', 'label' => __('media::media.col_file'), 'width' => 'minmax(0,1.6fr)'],
                ['key' => 'owner', 'label' => __('media::media.col_owner'), 'width' => 'minmax(0,1.4fr)'],
                ['key' => 'collection_name', 'label' => __('media::media.col_collection'), 'width' => '130px'],
                ['key' => 'mime_type', 'label' => __('media::media.col_type'), 'width' => '140px'],
                ['key' => 'size', 'label' => __('media::media.col_size'), 'width' => '90px'],
                ['key' => 'created_at', 'label' => __('media::media.col_created'), 'width' => '160px'],
            ],
            'filters' => $request->only(['q', 'owner', 'collection']),
            'filterOptions' => [
                'owners' => $owners->typeOptions(),
                'collections' => Media::query()->distinct()->orderBy('collection_name')->pluck('collection_name'),
            ],
            'urls' => [
                'index' => route('panel.media.index'),
                'store' => route('panel.media.store'),
                'show' => route('panel.media.show', '__ID__'),
                'update' => route('panel.media.update', '__ID__'),
            ],
        ]);
    }

    /**
     * Each file gets its own Asset as owner, so every media row has one.
     */
    public function store(MediaUploadRequest $request, AddsMedia $addsMedia): RedirectResponse
    {
        $files = $request->file('files');

        DB::transaction(function () use ($files, $addsMedia): void {
            foreach ($files as $file) {
                $addsMedia->execute(Asset::create(), $file);
            }
        });

        return back()->with('success', trans_choice('media::media.uploaded', count($files), ['count' => count($files)]));
    }

    public function show(Media $media, OwnerResolver $owners): JsonResponse
    {
        return response()->json($this->detail($media, $owners));
    }

    public function update(MediaUpdateRequest $request, Media $media, UpdatesMedia $updatesMedia, OwnerResolver $owners): JsonResponse
    {
        $updatesMedia->execute($media, $request->validated());

        return response()->json($this->detail($media->refresh(), $owners));
    }

    /**
     * Lunar's UpdatesMedia stores the editable name as a custom property, so it
     * wins over the `name` column that Spatie derives from the file name.
     */
    private function displayName(Media $media): string
    {
        return (string) $media->getCustomProperty('name', $media->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Media $media, OwnerResolver $owners): array
    {
        return [
            'id' => $media->id,
            'name' => $this->displayName($media),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => Number::fileSize($media->size),
            'collection_name' => $media->collection_name,
            'created_at' => $media->created_at?->toDateTimeString(),
            'original_url' => $media->getUrl(),
            'thumbnail' => str_starts_with((string) $media->mime_type, 'image/')
                ? $media->getAvailableUrl(['small'])
                : null,
            'alt' => $media->getCustomProperty('alt'),
            'caption' => $media->getCustomProperty('caption'),
            'owner' => $owners->describe($media->model, $media->model_type),
        ];
    }
}
