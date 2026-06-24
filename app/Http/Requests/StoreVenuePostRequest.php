<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVenuePostRequest extends FormRequest
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
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'title' => [
                'required',
                'string',
                'min:5',
                'max:200',
                'regex:/^[^\<\>]+$/u', // no html tags
            ],
            'short_description' => [
                'required',
                'string',
                'min:10',
                'max:500',
                'regex:/^[^\<\>]+$/u', // no html tags
            ],
            'content' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $stripped = trim(html_entity_decode(strip_tags($value)));
                    if (mb_strlen($stripped) < 20) {
                        $fail('Nội dung thực tế phải có ít nhất 20 ký tự (không tính mã HTML).');
                    }
                    if (mb_strlen($value) > 30000) {
                        $fail('Nội dung quá dài, tối đa 30000 ký tự.');
                    }
                    // Prevent XSS, script injection, iframe
                    if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $value)) {
                        $fail('Nội dung chứa mã script không hợp lệ.');
                    }
                    if (preg_match('/<iframe\b[^>]*>(.*?)<\/iframe>/is', $value)) {
                        $fail('Nội dung chứa iframe không hợp lệ.');
                    }
                    if (preg_match('/on\w+\s*=/i', $value)) {
                        $fail('Nội dung chứa thuộc tính HTML độc hại (event handler).');
                    }
                    if (preg_match('/javascript:/i', $value)) {
                        $fail('Nội dung chứa liên kết javascript độc hại.');
                    }
                },
            ],
            'meta_title' => ['nullable', 'string', 'max:255', 'regex:/^[^\<\>]+$/u'],
            'meta_description' => ['nullable', 'string', 'max:500', 'regex:/^[^\<\>]+$/u'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\s\-\p{L}]+$/u'],
            'thumbnail' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'post_type' => ['required', 'string', 'in:promotion,tournament,news,notice,recruitment'],
            'is_draft' => ['nullable', 'boolean']
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('title')) {
            $this->merge([
                'title' => trim($this->title),
            ]);
        }
    }
}
