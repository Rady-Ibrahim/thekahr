<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNearExpiryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255|unique:near_expiry_items,name',
            'image'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'expiry_date'      => 'required|date',
            'branch'           => 'nullable|string|max:150',
            'unit_price'       => 'required|numeric|min:0',
            'incentive_amount' => 'required|numeric|min:0',
            'stock_quantity'   => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'اسم الصنف مطلوب',
            'name.unique'               => 'هذا الصنف مسجل بالفعل',
            'image.image'               => 'الملف المرفق يجب أن يكون صورة',
            'image.max'                 => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت',
            'expiry_date.required'      => 'تاريخ الانتهاء مطلوب',
            'unit_price.required'       => 'سعر الوحدة مطلوب',
            'incentive_amount.required' => 'مبلغ الحافز لكل وحدة مطلوب',
            'stock_quantity.required'   => 'الكمية المتاحة مطلوبة',
        ];
    }
}
