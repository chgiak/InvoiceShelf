<?php

namespace App\Http\Controllers\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PDF;

class AccountantIncomeCountryReportController extends Controller
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

        // Get all invoices within the date range with additional fields
        $invoices = Invoice::with(['customer.billingAddress.country', 'payments.paymentMethod', 'taxes'])
            ->where('company_id', $company->id)
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('invoice_date', 'desc')
            ->get();

        // Group invoices by country
        $groupedInvoices = $invoices->groupBy(function ($invoice) {
            $country = $invoice->customer?->billingAddress?->country;
            return $country ? $country->id : 'no-country';
        })->map(function ($countryInvoices) {
            $firstInvoice = $countryInvoices->first();
            $country = $firstInvoice->customer?->billingAddress?->country;
            
            // Calculate totals for this country
            $totalAmount = $countryInvoices->sum('total');
            $totalTax = $countryInvoices->sum('tax');
            $totalSubTotal = $countryInvoices->sum('sub_total');
            $invoiceCount = $countryInvoices->count();
            
            // Count invoices by payment status
            $paidCount = $countryInvoices->where('paid_status', 'PAID')->count();
            $unpaidCount = $countryInvoices->where('paid_status', 'UNPAID')->count();
            $partiallyPaidCount = $countryInvoices->where('paid_status', 'PARTIALLY_PAID')->count();

            return [
                'country_name' => $country ? $country->name : 'Unknown',
                'country_code' => $country ? $country->code : null,
                'invoice_count' => $invoiceCount,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'amount_without_vat' => $totalSubTotal,
                'vat_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'invoices' => $countryInvoices,
            ];
        })->sortBy('country_name')->values();

        // Calculate totals by category
        $euTotal = 0;
        $ukTotal = 0;
        $homeCountryTotal = 0;
        $outsideEuTotal = 0;

        foreach ($groupedInvoices as $countryData) {
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
        $totalInvoiceCount = $groupedInvoices->sum('invoice_count');
        $totalPaidCount = $groupedInvoices->sum('paid_count');
        $totalUnpaidCount = $groupedInvoices->sum('unpaid_count');
        $totalPartiallyPaidCount = $groupedInvoices->sum('partially_paid_count');
        $totalWithoutVat = $groupedInvoices->sum('amount_without_vat');
        $totalVat = $groupedInvoices->sum('vat_amount');
        $totalAmount = $groupedInvoices->sum('total_amount');

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
            'groupedInvoices' => $groupedInvoices,
            'totalInvoiceCount' => $totalInvoiceCount,
            'totalPaidCount' => $totalPaidCount,
            'totalUnpaidCount' => $totalUnpaidCount,
            'totalPartiallyPaidCount' => $totalPartiallyPaidCount,
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

        $pdf = PDF::loadView('app.pdf.reports.accountant-income-country')
            ->setPaper('a4', 'landscape');

        if ($request->has('preview')) {
            return view('app.pdf.reports.accountant-income-country');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}