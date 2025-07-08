<?php

namespace App\Http\Controllers\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PDF;

class AccountantExpensesExpenseReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  string  $hash
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();

        if (!$company) {
            abort(404);
        }

        $this->authorize('view report', $company);

        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $start = Carbon::createFromFormat('Y-m-d', $request->from_date);
        $end = Carbon::createFromFormat('Y-m-d', $request->to_date);

        // Get all expenses within the date range with additional fields
        $expenses = Expense::with(['category', 'customer', 'paymentMethod'])
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('expense_date', 'desc')
            ->get()
            ->map(function ($expense) {
                // Since expenses don't have VAT fields, we'll show the full amount as including VAT
                // In a real implementation, you might want to add tax fields to the expense model
                $totalAmount = $expense->base_amount;

                // For now, we'll assume no VAT (0%) - you can adjust this based on your needs
                $vatRate = 0;
                $amountWithoutVat = $totalAmount;
                $vatAmount = 0;

                return [
                    'expense' => $expense,
                    'supplier' => $expense->customer ? $expense->customer->name : '-',
                    'vat_id' => $expense->customer ? $expense->customer->tax_id : '-',
                    'invoice_date' => $expense->expense_date,
                    'invoice_number' => $expense->expense_number,
                    'payment_method' => $expense->paymentMethod ? $expense->paymentMethod->name : '-',
                    'amount_without_vat' => $amountWithoutVat,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,
                ];
            });

        // Calculate totals
        $totalWithoutVat = $expenses->sum('amount_without_vat');
        $totalVat = $expenses->sum('vat_amount');
        $totalAmount = $expenses->sum('total_amount');

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id) ?: 'Y-m-d';
        $from_date = Carbon::createFromFormat('Y-m-d', $request->from_date)->translatedFormat($dateFormat);
        $to_date = Carbon::createFromFormat('Y-m-d', $request->to_date)->translatedFormat($dateFormat);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        $colors = [
            'primary_text_color',
            'heading_text_color',
            'section_heading_text_color',
            'border_color',
            'body_text_color',
            'footer_text_color',
            'footer_total_color',
            'footer_bg_color',
            'date_text_color',
        ];

        $colorSettings = CompanySetting::whereIn('option', $colors)
            ->whereCompany($company->id)
            ->get();

        view()->share([
            'expenses' => $expenses,
            'totalWithoutVat' => $totalWithoutVat,
            'totalVat' => $totalVat,
            'totalAmount' => $totalAmount,
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);

        $pdf = PDF::loadView('app.pdf.reports.accountant-expenses-expense')
            ->setPaper('a4', 'landscape');

        if ($request->has('preview')) {
            return view('app.pdf.reports.accountant-expenses-expense');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
