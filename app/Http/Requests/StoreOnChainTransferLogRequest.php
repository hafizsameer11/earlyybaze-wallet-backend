<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOnChainTransferLogRequest extends FormRequest
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
            'tx' => ['required', 'string', 'max:255', 'unique:on_chain_transfer_logs,tx'],
            'received_asset_id' => ['required', 'integer', 'exists:received_assets,id'],
            'gas_fee' => ['required', 'numeric', 'min:0'],
            'address_to_send' => ['required', 'string', 'max:255'],
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
