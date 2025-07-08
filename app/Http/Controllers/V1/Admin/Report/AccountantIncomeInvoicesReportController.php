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

class AccountantIncomeInvoicesReportController extends Controller
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
            ->orderBy('invoice_date', 'asc')
            ->get()
            ->map(function ($invoice) {
                // Calculate amounts
                $totalAmount = $invoice->total;
                $taxAmount = $invoice->tax;
                $amountWithoutVat = $invoice->sub_total;

                // Get payment method from the first payment (if exists)
                $paymentMethod = '-';
                if ($invoice->payments->count() > 0) {
                    $firstPayment = $invoice->payments->first();
                    $paymentMethod = $firstPayment->paymentMethod ? $firstPayment->paymentMethod->name : '-';
                }

                return [
                    'invoice' => $invoice,
                    'customer' => $invoice->customer ? $invoice->customer->name : '-',
                    'vat_id' => $invoice->customer ? $invoice->customer->tax_id : '-',
                    'invoice_date' => $invoice->invoice_date,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $invoice->paid_status,
                    'amount_without_vat' => $amountWithoutVat,
                    'vat_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ];
            });

        // Calculate totals
        $totalWithoutVat = $invoices->sum('amount_without_vat');
        $totalVat = $invoices->sum('vat_amount');
        $totalAmount = $invoices->sum('total_amount');

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
            'invoices' => $invoices,
            'totalWithoutVat' => $totalWithoutVat,
            'totalVat' => $totalVat,
            'totalAmount' => $totalAmount,
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);

        $pdf = PDF::loadView('app.pdf.reports.accountant-income-invoices')
            ->setPaper('a4', 'landscape');

        if ($request->has('preview')) {
            return view('app.pdf.reports.accountant-income-invoices');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
