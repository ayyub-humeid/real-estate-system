<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allowing anyone to register a company
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth('sanctum')->user();
        $isAuth = $user !== null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'agency_email' => ['nullable', 'string', 'email', 'max:255', 'unique:companies,email'],
            'agency_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'with_trial' => ['nullable', 'boolean'],
        ];

        if (!$isAuth) {
            $rules['admin_name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users,email'];
            $rules['phone'] = ['required', 'string', 'max:20'];
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        } else {
            $rules['admin_name'] = ['nullable', 'string', 'max:255'];
            $rules['email'] = ['nullable', 'string', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:20'];
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }
}
