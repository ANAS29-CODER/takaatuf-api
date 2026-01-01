<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string',
            'city_neighborhood' => 'required|string',
        ];
        
        if ($this->role == 'Knowledge Provider') {
            $rules['wallet_type'] = 'required|string';
            $rules['wallet_address'] = 'required|string';
        } else {
            $rules['paypal_account'] = 'required|string';
        }

        return $rules;
    }
}
