<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WithdrawRequest extends FormRequest
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
            'amount' => 'required|numeric|gt:0',
            'asset' => 'nullable|string',
            'bank_account_id' => 'nullable|numeric|exists:bank_accounts,id',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_code' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'status' => 'nullable|string',
            'fee' => 'nullable|numeric',
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
