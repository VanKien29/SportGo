<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVenuePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'string',
                'min:5',
                'max:200',
                'regex:/^[^\<\>]+$/u',
            ],
            'short_description' => [
                'sometimes',
                'string',
                'min:10',
                'max:500',
                'regex:/^[^\<\>]+$/u',
            ],
            'content' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    $stripped = trim(html_entity_decode(strip_tags($value)));
                    if (mb_strlen($stripped) < 20) {
                        $fail('Nội dung thực tế phải có ít nhất 20 ký tự (không tính mã HTML).');
                    }
                    if (mb_strlen($value) > 30000) {
                        $fail('Nội dung quá dài, tối đa 30000 ký tự.');
                    }
                },
            ],
            'meta_title' => ['nullable', 'string', 'max:255', 'regex:/^[^\<\>]+$/u'],
            'meta_description' => ['nullable', 'string', 'max:500', 'regex:/^[^\<\>]+$/u'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\s\-\p{L}]+$/u'],
            'thumbnail' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'removed_gallery_media_ids' => ['nullable', 'array', 'max:20'],
            'removed_gallery_media_ids.*' => ['integer', 'min:1', 'distinct'],
            'post_type' => ['sometimes', 'string', 'in:promotion,tournament,news,notice,recruitment'],
            'is_draft' => ['nullable', 'boolean']
        ];
    }

    public function prepareForValidation()
    {
        $merged = [];
        if ($this->has('title')) {
            $merged['title'] = trim(strip_tags($this->title));
        }
        if ($this->has('short_description')) {
            $merged['short_description'] = trim(strip_tags($this->short_description));
        }
        if ($this->has('meta_title')) {
            $merged['meta_title'] = trim(strip_tags($this->meta_title));
        }
        if ($this->has('meta_description')) {
            $merged['meta_description'] = trim(strip_tags($this->meta_description));
        }
        
        if (!empty($merged)) {
            $this->merge($merged);
        }
    }
}
