<?php

namespace LunarMedia\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LunarMedia\MediaSection;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(MediaSection::PERMISSION) ?? false;
    }

    /**
     * Same ceiling and image-only constraint the panel applies to product media.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'image', 'max:'.config('lunar.media.max_upload_kb', 8192)],
        ];
    }
}
