<?php

namespace App\Modules\Users\Http\Requests;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'group_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique(Permission::class, 'slug')],
            'description' => ['nullable', 'string', 'max:255'],
            'access_scope' => ['required', 'string', 'max:40'],
            'access_scope_label' => ['required', 'string', 'max:80'],
            'access_scope_badge_class' => ['required', 'string', 'max:80'],
            'access_scope_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
