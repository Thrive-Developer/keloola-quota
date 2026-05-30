<?php

namespace Keloola\Quota\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProvisionQuotaRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
    public function authorize(): bool
    {
        // Authorization is handled by the SSO signature middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'app_id'      => ['required'],
            'app_plan_id' => ['required'],
            'metrics'                  => ['required', 'array', 'min:1'],
            'metrics.*.name'           => ['required', 'string', 'max:255'],
            'metrics.*.code'           => ['required', 'string', 'max:255'],
            'metrics.*.type'           => ['required', 'in:snapshot,counter'],
            'metrics.*.unit'           => ['nullable', 'string', 'max:50'],
            'metrics.*.limit'          => ['nullable', 'integer', 'min:0'],
            'metrics.*.is_unlimited'   => ['nullable', 'boolean'],
            'metrics.*.is_active'      => ['nullable', 'boolean'],
        ];
    }
}
