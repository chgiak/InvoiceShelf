<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $rules = [
            'expense_date' => [
                'required',
            ],
            'expense_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'expense_category_id' => [
                'required',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'payment_method_id' => [
                'nullable',
            ],
            'amount' => [
                'required',
            ],
            'customer_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
            'currency_id' => [
                'required',
            ],
            'attachment_receipt' => [
                'nullable',
                'file',
                'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20000',
            ],
            'tax' => [
                'nullable',
                'numeric',
            ],
            'taxes' => [
                'nullable',
                'array',
            ],
            'taxes.*.name' => [
                'required_with:taxes',
            ],
            'taxes.*.calculation_type' => [
                'required_with:taxes',
                'in:percentage,fixed',
            ],
            'taxes.*.amount' => [
                'required_with:taxes',
                'numeric',
            ],
            'taxes.*.tax_type_id' => [
                'required_with:taxes',
                'numeric',
            ],
            'tax_per_item' => [
                'nullable',
                'string',
                'in:YES,NO',
            ],
            'sales_tax_type' => [
                'nullable',
                'string',
            ],
            'sales_tax_address_type' => [
                'nullable',
                'string',
            ],
        ];

        if ($companyCurrency && $this->currency_id) {
            if ($companyCurrency !== $this->currency_id) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        return $rules;
    }

    public function getExpensePayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;

        return collect($this->validated())
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $exchange_rate,
                'base_amount' => $this->amount * $exchange_rate,
                'currency_id' => $current_currency,
                'tax' => $this->tax ?: 0,
                'base_tax' => ($this->tax ?: 0) * $exchange_rate,
                'tax_per_item' => $this->tax_per_item ?: 'NO',
                'sales_tax_type' => $this->sales_tax_type,
                'sales_tax_address_type' => $this->sales_tax_address_type,
            ])
            ->toArray();
    }
}
