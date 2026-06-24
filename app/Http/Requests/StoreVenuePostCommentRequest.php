<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVenuePostCommentRequest extends FormRequest
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
            'content' => [
                'required',
                'string',
                'min:2',
                'max:1000',
                function ($attribute, $value, $fail) {
                    if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $value)) {
                        $fail('Nội dung chứa mã script không hợp lệ.');
                    }
                    if (preg_match('/<iframe\b[^>]*>(.*?)<\/iframe>/is', $value)) {
                        $fail('Nội dung chứa iframe không hợp lệ.');
                    }
                    if (trim($value) === '') {
                        $fail('Nội dung bình luận không được chỉ chứa khoảng trắng.');
                    }
                },
            ],
            'parent_id' => ['nullable', 'uuid', 'exists:venue_post_comments,id']
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge([
                'content' => trim(strip_tags($this->content)),
            ]);
        }
    }
}
