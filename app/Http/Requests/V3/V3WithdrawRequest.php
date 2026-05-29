<?php

namespace App\Http\Requests\V3;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class V3WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|gt:0',
            'currency' => 'required|in:ZAR,RAND',
            'bank_account_id' => 'nullable|numeric|exists:bank_accounts,id',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_code' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'fee' => 'nullable|numeric',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data' => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
