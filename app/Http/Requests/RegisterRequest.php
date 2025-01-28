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
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
            'phone' => 'required|unique:users,phone',
            'invite_code' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already in use.',
            'confirm_password.same' => 'Passwords do not match.'
        ];
    }
}
