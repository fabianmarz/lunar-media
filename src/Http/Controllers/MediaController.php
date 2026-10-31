<?php

namespace LunarMedia\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Core\Contracts\Actions\Media\AddsMedia;
use Lunar\Core\Contracts\Actions\Media\DeletesMedia;
use Lunar\Core\Contracts\Actions\Media\UpdatesMedia;
use Lunar\Core\Models\Asset;
use LunarMedia\Http\Requests\MediaUpdateRequest;
use LunarMedia\Http\Requests\MediaUploadRequest;
use LunarMedia\Support\MediaQuery;
use LunarMedia\Support\MediaUsage;
use LunarMedia\Support\OwnerResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController
{
    public function index(Request $request, OwnerResolver $owners, MediaQuery $query): Response
    {
        $media = $query->build($request)->paginate(25)->withQueryString();

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
            'filterOptions' => $query->filterOptions(),
            'urls' => [
                'index' => route('panel.media.index'),
                'store' => route('panel.media.store'),
                'show' => route('panel.media.show', '__ID__'),
                'update' => route('panel.media.update', '__ID__'),
                'destroy' => route('panel.media.destroy', '__ID__'),
                'picker' => route('panel.media.picker'),
            ],
        ]);
    }

    /**
     * Each file gets its own Asset as owner, so every media row has one.
     */
    public function store(MediaUploadRequest $request, AddsMedia $addsMedia, OwnerResolver $owners): RedirectResponse|JsonResponse
    {
        $files = $request->file('files');

        $created = DB::transaction(fn () => collect($files)
            ->map(fn ($file) => $addsMedia->execute(Asset::create(), $file)));

        if ($request->expectsJson()) {
            return response()->json(['data' => $created->map(fn (Media $media) => $this->pickerItem($media, $owners))->values()], 201);
        }

        return back()->with('success', trans_choice('media::media.uploaded', count($files), ['count' => count($files)]));
    }

    /**
     * Filtered, paginated JSON for the reusable picker dialog.
     */
    public function picker(Request $request, OwnerResolver $owners, MediaQuery $query): JsonResponse
    {
        $page = $query->build($request)->paginate(24)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $media) => $this->pickerItem($media, $owners))->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'filterOptions' => $query->filterOptions(),
        ]);
    }

    public function show(Media $media, OwnerResolver $owners, MediaUsage $usage): JsonResponse
    {
        return response()->json($this->detail($media, $owners) + ['usage' => $usage->pagesUsing($media->id)]);
    }

    /**
     * Refuses while a CMS block still references the file; otherwise deletes it
     * and, for a library upload, the Asset that only existed to own it.
     */
    public function destroy(Media $media, DeletesMedia $deletesMedia, MediaUsage $usage): JsonResponse
    {
        $pages = $usage->pagesUsing($media->id);

        if ($pages->isNotEmpty()) {
            return response()->json([
                'message' => __('media::media.in_use', ['pages' => $pages->pluck('title')->implode(', ')]),
                'errors' => ['media' => [__('media::media.in_use', ['pages' => $pages->pluck('title')->implode(', ')])]],
                'usage' => $pages,
            ], 422);
        }

        $owner = $media->model;

        DB::transaction(function () use ($media, $owner, $deletesMedia): void {
            $deletesMedia->execute($media);

            if ($owner instanceof Asset && $owner->media()->doesntExist()) {
                $owner->delete();
            }
        });

        return response()->json(['deleted' => true]);
    }

    public function update(MediaUpdateRequest $request, Media $media, UpdatesMedia $updatesMedia, OwnerResolver $owners): JsonResponse
    {
        $updatesMedia->execute($media, $request->validated());

        return response()->json($this->detail($media->refresh(), $owners));
    }

    /**
     * @return array<string, mixed>
     */
    private function pickerItem(Media $media, OwnerResolver $owners): array
    {
        return [
            'id' => $media->id,
            'name' => $this->displayName($media),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'url' => str_starts_with((string) $media->mime_type, 'image/') ? $media->getAvailableUrl(['small']) : null,
            'original_url' => $media->getUrl(),
            'owner' => $owners->describe($media->model, $media->model_type),
        ];
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
