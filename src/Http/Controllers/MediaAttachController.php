<?php

namespace LunarMedia\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use LunarMedia\Http\Requests\MediaAttachRequest;
use LunarMedia\Support\OwnerResolver;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaAttachController
{
    /**
     * Copy a library file onto a product, brand or collection. The entity gets
     * an independent file, so deleting the library original never breaks it.
     */
    public function __invoke(MediaAttachRequest $request, Media $media, OwnerResolver $owners): JsonResponse
    {
        $class = $owners->attachable()[$request->string('owner')->value()];

        /** @var HasMedia $target */
        $target = $class::query()->findOrFail($request->integer('owner_id'));

        $copy = $media->copy(
            $target,
            config('lunar.media.collection'),
            fileAdderCallback: fn (FileAdder $adder) => $adder->withCustomProperties(Arr::except($media->custom_properties, ['primary'])),
        );

        return response()->json(['id' => $copy->id, 'url' => $copy->getUrl()], 201);
    }
}
