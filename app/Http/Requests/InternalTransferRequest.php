<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InternalTransferRequest extends FormRequest
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
            'currency' => 'required|string',
            'network' => 'required|string',
            'email' => 'nullable',
            'address' => 'nullable|string',
            'amount' => 'required|numeric',

            'fee_summary' => 'nullable|array',
            'fee_summary.platform_fee_usd' => 'required_with:fee_summary|numeric',
            'fee_summary.network_fee_usd' => 'required_with:fee_summary|numeric',
            'fee_summary.total_fee_usd' => 'required_with:fee_summary|numeric',
            'fee_summary.amount_after_fee' => 'required_with:fee_summary|numeric',
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
