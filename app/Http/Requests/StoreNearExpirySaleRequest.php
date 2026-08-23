<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNearExpirySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'near_expiry_item_id' => 'required|exists:near_expiry_items,id',
            'employee_id'         => 'nullable|exists:employees,id',
            'quantity_sold'       => 'required|integer|min:1',
            'branch'              => 'nullable|string|max:150',
            'invoice_number'      => 'nullable|string|max:100',
            'invoice_date'        => 'required|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'near_expiry_item_id.required' => 'اختيار الصنف مطلوب',
            'near_expiry_item_id.exists'   => 'الصنف غير موجود',
            'quantity_sold.required'       => 'الكمية المباعة مطلوبة',
            'quantity_sold.min'            => 'الكمية يجب أن تكون وحدة واحدة على الأقل',
            'invoice_date.required'        => 'تاريخ الفاتورة مطلوب',
            'invoice_date.before_or_equal' => 'تاريخ الفاتورة لا يمكن أن يكون في المستقبل',
        ];
    }
}
