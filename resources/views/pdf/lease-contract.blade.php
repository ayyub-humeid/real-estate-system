<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lease Contract - {{ $lease->id }}</title>
    <style>
        @page {
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            position: relative;
        }
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.05;
            z-index: -1;
        }
        .container {
            padding: 40px 50px;
            position: relative;
            z-index: 1;
        }
        .header {
            background: {{ $settings->lease_header_color ?? '#1e40af' }};
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 24pt;
            margin-bottom: 10px;
        }
        .logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
        .company-info {
            font-size: 10pt;
            margin-top: 10px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: {{ $settings->lease_header_color ?? '#1e40af' }};
            border-bottom: 2px solid {{ $settings->lease_header_color ?? '#1e40af' }};
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 180px;
            color: #555;
        }
        .info-value {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
        }
        .terms {
            background: #f9fafb;
            border-left: 4px solid {{ $settings->lease_header_color ?? '#1e40af' }};
            padding: 15px;
            margin: 20px 0;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 2px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }
        .signature-image {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }
        .footer {
            text-align: center;
            font-size: 9pt;
            color: #888;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    @if($settings && $settings->lease_background)
        <img src="{{ public_path('storage/' . $settings->lease_background) }}" class="background" alt="">
    @endif

    <div class="container">
        <!-- Header -->
        <div class="header">
            @if($settings && $settings->logo)
                <img src="{{ public_path('storage/' . $settings->logo) }}" class="logo" alt="Company Logo">
            @endif
            <h1>LEASE AGREEMENT</h1>
            <div class="company-info">
                @if($settings)
                    <div>{{ $settings->company_legal_name ?? $lease->company->name }}</div>
                    @if($settings->company_address)
                        <div>{{ $settings->company_address }}</div>
                    @endif
                    <div>
                        @if($settings->company_phone) Tel: {{ $settings->company_phone }} | @endif
                        @if($settings->company_email) Email: {{ $settings->company_email }} @endif
                    </div>
                    @if($settings->tax_id)
                        <div>Tax ID: {{ $settings->tax_id }}</div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Lease Information -->
        <div class="section">
            <div class="section-title">LEASE INFORMATION</div>
            <div class="info-row">
                <div class="info-label">Contract Number:</div>
                <div class="info-value">{{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Contract Date:</div>
                <div class="info-value">{{ $lease->created_at->format('F d, Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Start Date:</div>
                <div class="info-value">{{ $lease->start_date->format('F d, Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">End Date:</div>
                <div class="info-value">{{ $lease->end_date ? $lease->end_date->format('F d, Y') : 'Open-ended' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">{{ strtoupper($lease->status) }}</div>
            </div>
        </div>

        <!-- Property & Unit -->
        <div class="section">
            <div class="section-title">PROPERTY DETAILS</div>
            <div class="info-row">
                <div class="info-label">Property Name:</div>
                <div class="info-value">{{ $lease->unit->property->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Unit Number:</div>
                <div class="info-value">{{ $lease->unit->unit_number }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Unit Type:</div>
                <div class="info-value">{{ ucfirst($lease->unit->type ?? 'N/A') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value">{{ $lease->unit->property->address }}</div>
            </div>
        </div>

        <!-- Tenant Information -->
        <div class="section">
            <div class="section-title">TENANT INFORMATION</div>
            <div class="info-row">
                <div class="info-label">Tenant Name:</div>
                <div class="info-value">{{ $lease->tenant->user->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $lease->tenant->user->email }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $lease->tenant->user->phone ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- Financial Terms -->
        <div class="section">
            <div class="section-title">FINANCIAL TERMS</div>
            <table>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
                <tr>
                    <td>Monthly Rent</td>
                    <td style="text-align: right;">${{ number_format($lease->rent_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Security Deposit</td>
                    <td style="text-align: right;">${{ number_format($lease->deposit_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Payment Frequency</td>
                    <td style="text-align: right;">{{ ucfirst($lease->payment_frequency) }}</td>
                </tr>
                <tr>
                    <td>Payment Day</td>
                    <td style="text-align: right;">{{ $lease->payment_day }} of each month</td>
                </tr>
            </table>
        </div>

        <!-- Terms & Conditions -->
        @if($settings && $settings->lease_terms)
        <div class="section">
            <div class="section-title">TERMS & CONDITIONS</div>
            <div class="terms">
                {!! nl2br(e($settings->lease_terms)) !!}
            </div>
        </div>
        @endif

        @if($lease->special_terms)
        <div class="section">
            <div class="section-title">SPECIAL TERMS</div>
            <div class="terms">
                {!! nl2br(e($lease->special_terms)) !!}
            </div>
        </div>
        @endif

        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-box">
                @if($settings && $settings->signature)
                    <img src="{{ public_path('storage/' . $settings->signature) }}" class="signature-image" alt="Company Signature">
                @endif
                <div class="signature-line">
                    <strong>{{ $settings->company_legal_name ?? $lease->company->name }}</strong><br>
                    Landlord / Authorized Representative
                </div>
            </div>

            <div class="signature-box">
                <div class="signature-line">
                    <strong>{{ $lease->tenant->user->name }}</strong><br>
                    Tenant
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            @if($settings && $settings->lease_footer_text)
                {{ $settings->lease_footer_text }}
            @else
                This is a legally binding agreement. Please read carefully before signing.
            @endif
            <br>
            Contract ID: {{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }} | Generated on {{ now()->format('F d, Y H:i') }}
        </div>
    </div>
</body>
</html>