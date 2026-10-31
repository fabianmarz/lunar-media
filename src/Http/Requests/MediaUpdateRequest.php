<?php

namespace LunarMedia\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LunarMedia\MediaSection;

class MediaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(MediaSection::PERMISSION) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
