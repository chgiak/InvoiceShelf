<!DOCTYPE html>
<html lang="en">

<head>
    <title>@lang('pdf_accountant_invoices_county_report_label')</title>
    <style type="text/css">
        body {
            font-family: "DejaVu Sans";
        }

        table {
            border-collapse: collapse;
        }

        .sub-container {
            padding: 0px 20px;
        }

        .report-header {
            width: 100%;
        }

        .heading-text {
            font-weight: bold;
            font-size: 24px;
            color: #5851D8;
            width: 100%;
            text-align: left;
            padding: 0px;
            margin: 0px;
        }

        .heading-date-range {
            font-weight: normal;
            font-size: 15px;
            color: #A5ACC1;
            width: 100%;
            text-align: right;
            padding: 0px;
            margin: 0px;
        }

        .invoice-detail-table {
            width: 100%;
            margin-top: 30px;
            font-size: 11px;
        }

        .invoice-detail-table thead {
            background-color: #F3F4F6;
        }

        .invoice-detail-table th {
            font-weight: bold;
            color: #595959;
            padding: 10px 5px;
            text-align: left;
            border-bottom: 1px solid #EAF1FB;
        }

        .invoice-detail-table td {
            font-weight: normal;
            color: #595959;
            padding: 10px 5px;
            vertical-align: top;
            border-bottom: 1px solid #EAF1FB;
        }

        .invoice-detail-table th:last-child,
        .invoice-detail-table td:last-child {
            text-align: right;
        }

        .invoice-detail-table th:nth-last-child(2),
        .invoice-detail-table td:nth-last-child(2),
        .invoice-detail-table th:nth-last-child(3),
        .invoice-detail-table td:nth-last-child(3) {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            font-size: 12px;
            background-color: #F3F4F6;
        }

        .total-row td {
            padding: 12px 5px;
            border-bottom: 2px solid #5851D8;
        }

        .summary-table {
            width: 350px;
            margin-top: 30px;
            margin-left: auto;
        }

        .summary-table td {
            padding: 8px 10px;
            font-size: 12px;
        }

        .summary-label {
            text-align: left;
            color: #595959;
        }

        .summary-value {
            text-align: right;
            color: #595959;
            font-weight: bold;
        }

        .summary-total {
            font-size: 14px;
            font-weight: bold;
            color: #5851D8;
            border-top: 1px solid #EAF1FB;
        }

        .summary-subtotal {
            border-top: 1px solid #EAF1FB;
        }

        .status-count {
            font-size: 10px;
        }

        .status-paid {
            color: #10B981;
        }

        .status-unpaid {
            color: #EF4444;
        }

        .status-partial {
            color: #F59E0B;
        }
    </style>
</head>

<body>
    <div class="sub-container">
        <table class="report-header">
            <tr>
                <td>
                    <p class="heading-text">{{ $company->name }}</p>
                </td>
                <td>
                    <p class="heading-date-range">{{ $from_date }} - {{ $to_date }}</p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <p class="sub-heading-text">@lang('pdf_accountant_invoices_county_report_label')</p>
                </td>
            </tr>
        </table>

        <!-- Invoice Details Table -->
        <table class="invoice-detail-table">
            <thead>
                <tr>
                    <th style="width: 20%">@lang('country')</th>
                    <th style="width: 10%">@lang('pdf_invoice_count')</th>
{{--                    <th style="width: 20%">@lang('pdf_payment_status')</th>--}}
                    <th style="width: 16%">@lang('pdf_price_label')</th>
                    <th style="width: 16%">@lang('pdf_price_vat_label')</th>
                    <th style="width: 18%">@lang('pdf_subtotal')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groupedInvoices as $countryData)
                <tr>
                    <td>{{ $countryData['country_name'] }}</td>
                    <td>{{ $countryData['invoice_count'] }}</td>
                    <td>{!! format_money_pdf($countryData['amount_without_vat'], $currency) !!}</td>
                    <td>{!! format_money_pdf($countryData['vat_amount'], $currency) !!}</td>
                    <td>{!! format_money_pdf($countryData['total_amount'], $currency) !!}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>@lang('pdf_total')</td>
                    <td>{{ $totalInvoiceCount }}</td>
                    <td>{!! format_money_pdf($totalWithoutVat, $currency) !!}</td>
                    <td>{!! format_money_pdf($totalVat, $currency) !!}</td>
                    <td>{!! format_money_pdf($totalAmount, $currency) !!}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Summary by Region -->
        <table class="summary-table">
            <tr class="summary-subtotal">
                <td class="summary-label">@lang('pdf_total_invoices'):</td>
                <td class="summary-value">{{ $totalInvoiceCount }}</td>
            </tr>
            <tr>
                <td class="summary-label">@lang('pdf_price_label'):</td>
                <td class="summary-value">{!! format_money_pdf($totalWithoutVat, $currency) !!}</td>
            </tr>
            <tr>
                <td class="summary-label">@lang('pdf_price_vat_label'):</td>
                <td class="summary-value">{!! format_money_pdf($totalVat, $currency) !!}</td>
            </tr>
            <tr class="summary-total">
                <td class="summary-label">@lang('pdf_total'):</td>
                <td class="summary-value">{!! format_money_pdf($totalAmount, $currency) !!}</td>
            </tr>
        </table>
        <table class="summary-table">
            <tr>
                <td class="summary-label">@lang('pdf_income_eu_countries'):</td>
                <td class="summary-value">{!! format_money_pdf($euTotal, $currency) !!}</td>
            </tr>
            <tr>
                <td class="summary-label">@lang('pdf_income_home_country') ({{ $homeCountryName }}):</td>
                <td class="summary-value">{!! format_money_pdf($homeCountryTotal, $currency) !!}</td>
            </tr>
            <tr>
                <td class="summary-label">@lang('pdf_income_uk'):</td>
                <td class="summary-value">{!! format_money_pdf($ukTotal, $currency) !!}</td>
            </tr>
            <tr>
                <td class="summary-label">@lang('pdf_income_outside_eu'):</td>
                <td class="summary-value">{!! format_money_pdf($outsideEuTotal, $currency) !!}</td>
            </tr>
        </table>
    </div>
</body>

</html>
