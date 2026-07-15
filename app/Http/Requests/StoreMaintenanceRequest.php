<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الحماية الحقيقية (تأكيد ملكية الوحدة) تصير بالـ Controller،
        // هنا فقط نتأكد إن المستخدم tenant
        return $this->user()->isTenant();
    }

    public function rules(): array
    {
        return [
            'unit_id'     => ['required', 'integer', 'exists:units,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'priority'    => ['nullable', 'in:low,medium,high,emergency'],
            'images'      => ['nullable', 'array', 'max:5'],
            'images.*'    => ['image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
