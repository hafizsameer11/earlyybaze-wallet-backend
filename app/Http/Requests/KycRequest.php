<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class KycRequest extends FormRequest
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
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'dob' => 'required|string',
            'address' => 'required|string',
            'state' => 'required|string',
            'bvn' => 'required|string',
            'document_type' => 'required|string',
            'document_number' => 'required|string',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,pdf|max:2048',
            'document_front' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,pdf|max:2048',
            'document_back' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,pdf|max:2048'

        ];
    }
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422)
        );
    }
}
