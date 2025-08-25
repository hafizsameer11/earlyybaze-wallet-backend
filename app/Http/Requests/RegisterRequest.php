<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'phone' => 'required|unique:users,phone',
            'invite_code' => 'nullable',
            'profile_picture' => 'nullable',
            'role'=>'nullable',
            'pin' => 'nullable',
            'fullName'=>'nullable'
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already in use.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        // Throw a JSON response when validation fails
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422)
        );
    }
}
