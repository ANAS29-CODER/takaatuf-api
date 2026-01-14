<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateKnowledgeRequest extends FormRequest
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
            'category' => 'required|in:Survey,Essay,Photo,Video,Errand',
            'details' => 'required|string|min:50',

            // Pay per KP
            'pay_per_kp' => [
                'required',
                'numeric',
                'min:0.01',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],

            // Number of providers
            'number_of_kps' => 'required|integer|min:1',

            // Neighborhood
            'neighborhood' => 'required|string',

            // Media (optional)
            'media.*' => 'nullable|file',
        ];
    }

       public function messages(): array
    {
        return [
            'category.required' => 'Category is required.',
            'details.required' => 'Details are required.',
            'details.min' => 'Details must be at least 50 characters.',
            'pay_per_kp.required' => 'Pay per KP is required.',
            'pay_per_kp.numeric' => 'Pay per KP must be a valid number.',
            'number_of_kps.required' => 'Number of KPs is required.',
            'number_of_kps.integer' => 'Number of KPs must be an integer.',
            'neighborhood.required' => 'Neighborhood is required.',
        ];
    } 

}
