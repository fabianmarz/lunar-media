<?php

namespace LunarMedia\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use LunarMedia\Support\OwnerResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController
{
    public function index(Request $request, OwnerResolver $owners): Response
    {
        $media = Media::query()
            ->with(['model' => fn (MorphTo $morph) => $morph->morphWith($owners->eagerLoads())])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $media->setCollection($media->getCollection()->map(fn (Media $item): array => [
            'id' => $item->id,
            'name' => $item->name,
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
            'urls' => ['index' => route('panel.media.index')],
        ]);
    }
}
