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

class AccountantExpensesCountryReportController extends Controller
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

        // Define EU country codes
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        // Get company's home country (assuming it's stored in company's billing address)
        $homeCountryCode = $company->address?->country?->code ?? null;

        // Get all expenses within the date range with additional fields
        $expenses = Expense::with(['category', 'customer.billingAddress.country', 'paymentMethod'])
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('expense_date', 'desc')
            ->get();

        // Group expenses by country
        $groupedExpenses = $expenses->groupBy(function ($expense) {
            $country = $expense->customer?->billingAddress?->country;
            return $country ? $country->id : 'no-country';
        })->map(function ($countryExpenses) {
            $firstExpense = $countryExpenses->first();
            $country = $firstExpense->customer?->billingAddress?->country;

            // Calculate totals for this country
            $totalAmount = $countryExpenses->sum('base_amount');
            $expenseCount = $countryExpenses->count();

            // For now, we'll assume no VAT (0%) - you can adjust this based on your needs
            $vatRate = 0;
            $amountWithoutVat = $totalAmount;
            $vatAmount = 0;

            return [
                'country_name' => $country ? $country->name : 'Unknown',
                'country_code' => $country ? $country->code : null,
                'expense_count' => $expenseCount,
                'amount_without_vat' => $amountWithoutVat,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'expenses' => $countryExpenses,
            ];
        })->sortBy('country_name')->values();

        // Calculate totals by category
        $euTotal = 0;
        $ukTotal = 0;
        $homeCountryTotal = 0;
        $outsideEuTotal = 0;

        foreach ($groupedExpenses as $countryData) {
            $countryCode = $countryData['country_code'];
            $amount = $countryData['total_amount'];

            if ($countryCode === $homeCountryCode) {
                $homeCountryTotal += $amount;
            }

            if ($countryCode === 'GB') {
                $ukTotal += $amount;
            } elseif (in_array($countryCode, $euCountries)) {
                $euTotal += $amount;
            } else if ($countryCode && $countryCode !== $homeCountryCode) {
                $outsideEuTotal += $amount;
            }
        }

        // Calculate grand totals
        $totalExpenseCount = $groupedExpenses->sum('expense_count');
        $totalWithoutVat = $groupedExpenses->sum('amount_without_vat');
        $totalVat = $groupedExpenses->sum('vat_amount');
        $totalAmount = $groupedExpenses->sum('total_amount');

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
            'groupedExpenses' => $groupedExpenses,
            'totalExpenseCount' => $totalExpenseCount,
            'totalWithoutVat' => $totalWithoutVat,
            'totalVat' => $totalVat,
            'totalAmount' => $totalAmount,
            'euTotal' => $euTotal,
            'ukTotal' => $ukTotal,
            'homeCountryTotal' => $homeCountryTotal,
            'homeCountryName' => $company->address?->country?->name ?? 'Unknown',
            'outsideEuTotal' => $outsideEuTotal,
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);

        $pdf = PDF::loadView('app.pdf.reports.accountant-expenses-country')
            ->setPaper('a4', 'landscape');

        if ($request->has('preview')) {
            return view('app.pdf.reports.accountant-expenses-country');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
