<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Post|null $post */
        $post = $this->route('post');

        return [
            'title'        => ['sometimes', 'string', 'max:255'],
            'slug'         => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($post?->id),
            ],
            'excerpt'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'body'         => ['sometimes', 'string', 'not_regex:/data:image\//'],
            'type'         => ['sometimes', 'string', 'in:noticia,nuevo_curso,oferta,evento,lanzamiento,certificacion,contenido'],
            'is_featured'  => ['sometimes', 'boolean'],
            'cta_label'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'cta_url'      => ['sometimes', 'nullable', 'url'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'cover_image'  => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
