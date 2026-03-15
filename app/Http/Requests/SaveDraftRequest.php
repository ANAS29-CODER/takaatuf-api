<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text_content' => 'nullable|string|max:65535',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:102400|mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'text_content.max' => 'The text content is too long. Maximum 65535 characters allowed.',
            'files.max' => 'You can only upload up to 10 files at a time.',
            'files.*.max' => 'Each file must be less than 100MB.',
            'files.*.mimetypes' => 'Invalid file type. Allowed types: images (jpg, png, gif, webp), videos (mp4, mov, avi, webm), and documents (pdf, doc, docx).',
        ];
    }
}
