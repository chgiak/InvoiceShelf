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

class AccountantIncomeCustomerReportController extends Controller
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

        // Get all invoices within the date range with additional fields
        $invoices = Invoice::with(['customer', 'payments.paymentMethod', 'taxes'])
            ->where('company_id', $company->id)
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('invoice_date', 'desc')
            ->get();

        // Group invoices by customer
        $groupedInvoices = $invoices->groupBy(function ($invoice) {
            return $invoice->customer ? $invoice->customer->id : 'no-customer';
        })->map(function ($customerInvoices) {
            $customer = $customerInvoices->first()->customer;
            
            // Calculate totals for this customer
            $totalAmount = $customerInvoices->sum('total');
            $totalTax = $customerInvoices->sum('tax');
            $totalSubTotal = $customerInvoices->sum('sub_total');
            $invoiceCount = $customerInvoices->count();
            
            // Count invoices by payment status
            $paidCount = $customerInvoices->where('paid_status', 'PAID')->count();
            $unpaidCount = $customerInvoices->where('paid_status', 'UNPAID')->count();
            $partiallyPaidCount = $customerInvoices->where('paid_status', 'PARTIALLY_PAID')->count();

            return [
                'customer' => $customer ? $customer->name : '-',
                'vat_id' => $customer ? $customer->tax_id : '-',
                'invoice_count' => $invoiceCount,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'amount_without_vat' => $totalSubTotal,
                'vat_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'invoices' => $customerInvoices,
            ];
        })->values();

        // Calculate totals
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
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);

        $pdf = PDF::loadView('app.pdf.reports.accountant-income-customer')
            ->setPaper('a4', 'landscape');

        if ($request->has('preview')) {
            return view('app.pdf.reports.accountant-income-customer');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}