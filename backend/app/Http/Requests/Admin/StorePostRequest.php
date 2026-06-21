<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['sometimes', 'nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'body'         => ['required', 'string', 'not_regex:/data:image\//i'],
            'type'         => ['required', 'string', 'in:noticia,nuevo_curso,oferta,evento,lanzamiento,certificacion,contenido'],
            'is_featured'  => ['sometimes', 'boolean'],
            'cta_label'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'cta_url'      => ['sometimes', 'nullable', 'url'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'cover_image'  => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
