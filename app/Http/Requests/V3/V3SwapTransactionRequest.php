<?php

namespace App\Http\Requests\V3;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class V3SwapTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => 'required|string',
            'network' => 'required|string',
            'amount' => 'required|numeric|gt:0',
            'exchange_rate' => 'required',
            'fiat_currency' => 'required|in:ZAR,RAND',
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
