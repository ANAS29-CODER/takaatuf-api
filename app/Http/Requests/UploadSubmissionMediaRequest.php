<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSubmissionMediaRequest extends FormRequest
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
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:102400|mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one file to upload.',
            'files.array' => 'Invalid file format.',
            'files.min' => 'Please select at least one file to upload.',
            'files.max' => 'You can only upload up to 10 files at a time.',
            'files.*.required' => 'Each file is required.',
            'files.*.file' => 'Invalid file upload.',
            'files.*.max' => 'Each file must be less than 100MB.',
            'files.*.mimetypes' => 'Invalid file type. Allowed types: images (jpg, png, gif, webp), videos (mp4, mov, avi, webm), and documents (pdf, doc, docx).',
        ];
    }
}
