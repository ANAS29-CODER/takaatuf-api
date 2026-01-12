<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeRequest extends FormRequest
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
  public function rules()
    {
        return [
            'category' => 'required|in:survey,essay,photos,videos,errand',

            'details' => 'required|string|min:50',

            'pay_per_kp' => 'required|numeric|min:0.01|regex:/^\d+(\.\d{1,2})?$/',

            'number_of_providers' => 'required|integer|min:1',

            'neighborhood' => 'required|string',

            'attachments.*' => 'nullable|file|mimetypes:image/jpeg,image/png,video/mp4|max:102400'
        ];
    }
}
