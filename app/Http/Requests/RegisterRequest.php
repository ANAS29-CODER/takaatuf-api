<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'full_name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|regex:/[A-Z]/|regex:/[a-z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
            'password_confirmation' => 'required|same:password',
        ];
    }

  public function messages()
{
    return [

        // Full Name
        'full_name.required' => 'Full name is required.',
        'full_name.string'   => 'Full name must be a valid text.',
        'full_name.min'      => 'Full name must be at least 2 characters long.',
        'full_name.max'      => 'Full name must not exceed 100 characters.',

        // Email
        'email.required' => 'Email is required.',
        'email.email'    => 'Please enter a valid email address.',
        'email.unique'   => 'This email is already registered. Please sign in ".',

        // Password
        'password.required' => 'Password is required.',
        'password.min'      => 'Password must be at least 8 characters long.',
        'password.regex'    => 'Password must include at least one uppercase letter, one lowercase letter, one number, and one special character.',

        // Password Confirmation
        'password_confirmation.required' => 'Password confirmation is required.',
        'password_confirmation.same'     => 'Password confirmation does not match the password.',
    ];
}
}
