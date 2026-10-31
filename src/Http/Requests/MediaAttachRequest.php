<?php

namespace LunarMedia\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LunarMedia\MediaSection;
use LunarMedia\Support\OwnerResolver;

class MediaAttachRequest extends FormRequest
{
    /**
     * Attaching writes to a shop entity, so the staff member needs both the
     * library permission and the panel permission that guards that entity.
     */
    public function authorize(): bool
    {
        $permission = app(OwnerResolver::class)->attachPermission($this->string('owner')->value());

        return ($this->user()?->can(MediaSection::PERMISSION) ?? false)
            && ($permission === null || ($this->user()?->can($permission) ?? false));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner' => ['required', 'string', Rule::in(array_keys(app(OwnerResolver::class)->attachable()))],
            'owner_id' => ['required', 'integer'],
        ];
    }
}
