<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNearExpiryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('id');

        return [
            'name'             => 'sometimes|required|string|max:255|unique:near_expiry_items,name,' . $itemId,
            'image'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_image'     => 'nullable|boolean',
            'expiry_date'      => 'sometimes|required|date',
            'branch'           => 'nullable|string|max:150',
            'unit_price'       => 'sometimes|required|numeric|min:0',
            'incentive_amount' => 'sometimes|required|numeric|min:0',
            'stock_quantity'   => 'sometimes|required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'اسم الصنف مطلوب',
            'name.unique'               => 'هذا الصنف مسجل بالفعل',
            'image.image'               => 'الملف المرفق يجب أن يكون صورة',
            'image.max'                 => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت',
        ];
    }
}
