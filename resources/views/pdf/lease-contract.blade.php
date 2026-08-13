{{--
resources/views/pdf/lease-contract.blade.php
─────────────────────────────────────────────
Official Lease Agreement PDF — A4 single-page optimised.
Uses DomPDF-compatible CSS (no flexbox — tables for layout).
All images are passed as base64 data URIs via $imageData[].
--}}
@php
    // ── Resolve dynamic values ──
    $headerColor = $settings->lease_header_color ?? '#1a237e';
    $headerColorRgb = $headerColor; // direct hex for DomPDF
    $companyName = $settings->company_legal_name ?? $lease->company->name ?? 'Property Management Company';
    $companyAddr = $settings->company_address ?? $lease->company->address ?? '';
    $companyPhone = $settings->company_phone ?? $lease->company->phone ?? '';
    $companyEmail = $settings->company_email ?? $lease->company->email ?? '';
    $companyWeb = $settings->website ?? '';
    $taxId = $settings->tax_id ?? '';
    $regNo = $settings->registration_number ?? '';

    // Lease target
    $propertyName = '';
    $unitNumber = '';
    $unitType = '';
    $address = '';
    if ($lease->unit) {
        $propertyName = $lease->unit->property->name ?? 'N/A';
        $unitNumber = $lease->unit->unit_number ?? 'N/A';
        $unitType = ucfirst($lease->unit->type ?? 'Unit');
        $address = $lease->unit->property->location->full_path ?? $lease->unit->property->address ?? '';
    } elseif ($lease->property) {
        $propertyName = $lease->property->name ?? 'N/A';
        $unitNumber = 'Whole Property';
        $unitType = 'Full Property';
        $address = $lease->property->location->full_path ?? $lease->unit->property->address ?? '';

    }

    // Tenant info
    $tenantName = $lease->tenant->user->name ?? 'N/A';
    $tenantEmail = $lease->tenant->user->email ?? 'N/A';
    $tenantPhone = $lease->tenant->user->phone ?? 'N/A';
    $tenantIdType = ucfirst($lease->tenant->id_type ?? 'ID');
    $tenantIdNum = $lease->tenant->id_number ?? 'N/A';
    $employer = $lease->tenant->employer_name ?? 'N/A';
    $occupants = $lease->tenant->number_of_occupants ?? 1;

    // Dates & duration
    $issueDate = $lease->created_at ? $lease->created_at->format('d F Y') : now()->format('d F Y');
    $startDate = $lease->start_date ? $lease->start_date->format('d F Y') : 'N/A';
    $endDate = $lease->end_date ? $lease->end_date->format('d F Y') : 'Open-ended';
    $duration = $lease->end_date
        ? $lease->start_date->diffInMonths($lease->end_date) . ' months'
        : 'Open-ended';

    // Financial
    $rentAmount = number_format((float) $lease->rent_amount, 2);
    $depositAmount = number_format((float) $lease->deposit_amount, 2);
    $frequency = ucfirst(str_replace('_', '-', $lease->payment_frequency ?? 'monthly'));
    $paymentDay = $lease->payment_day ?? 1;

    // Installments
    $freqMap = ['monthly' => 1, 'quarterly' => 3, 'semi_annually' => 6, 'yearly' => 12];
    $freqMonths = $freqMap[$lease->payment_frequency] ?? 1;
    $totalMonths = $lease->end_date
        ? max(1, (int) $lease->start_date->diffInMonths($lease->end_date))
        : 12;
    $numInstallments = max(1, (int) ceil($totalMonths / $freqMonths));
    $installmentAmt = $numInstallments > 0
        ? number_format((float) $lease->rent_amount / $numInstallments, 2)
        : $rentAmount;

    // Status
    $statusLabel = ucfirst($lease->status ?? 'draft');
    $statusColors = [
        'active' => '#2e7d32',
        'draft' => '#757575',
        'expired' => '#c62828',
        'terminated' => '#e65100',
        'renewed' => '#1565c0',
    ];
    $statusColor = $statusColors[$lease->status] ?? '#757575';

    // Contract number
    $contractNo = '#' . str_pad($lease->id, 6, '0', STR_PAD_LEFT);

    // Terms
    $leaseTerms = $settings->lease_terms ?? null;
    $footerText = $settings->lease_footer_text ?? null;
    $specialTerms = $lease->special_terms ?? null;
    $notes = $lease->notes ?? null;

    // Ordinal suffix for payment day
    $ordSuffix = match (true) {
        in_array($paymentDay % 100, [11, 12, 13]) => 'th',
        $paymentDay % 10 === 1 => 'st',
        $paymentDay % 10 === 2 => 'nd',
        $paymentDay % 10 === 3 => 'rd',
        default => 'th',
    };
    $payDayLabel = $paymentDay . $ordSuffix . ' of each month';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Lease Agreement {{ $contractNo }}</title>
    <style>
        /* ── Reset & Base ── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a1a2e;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        /* ── Page Container ── */
        .page {
            position: relative;
            width: 100%;
            min-height: 100%;
            padding: 0;
            margin: 0;
        }

        /* ── Background Image (watermark behind content) ── */
        .bg-watermark {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.2;
            z-index: 0;
        }

        .bg-watermark img {
            width: 100%;
            height: 100%;
        }

        /* ── Header Band ── */
        .header-band {
            background-color:
                {{ $headerColor }}
            ;
            color: #ffffff;
            padding: 18px 35px 14px 35px;
            position: relative;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .header-logo {
            width: 70px;
        }

        .header-logo img {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .header-title {
            text-align: center;
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #ffffff;
            margin: 0 0 2px 0;
        }

        .header-title .company-name {
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.85;
        }

        .header-meta {
            width: 120px;
            text-align: right;
        }

        .contract-badge {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            display: inline-block;
        }

        .header-date {
            font-size: 8px;
            opacity: 0.7;
            margin-top: 4px;
        }

        /* ── Accent stripe under header ── */
        .accent-stripe {
            height: 3px;
            background: linear-gradient(90deg,
                    {{ $headerColor }}
                    ,
                    {{ $headerColor }}
                    88,
                    {{ $headerColor }}
                    33, transparent);
        }

        /* ── Content Area ── */
        .content {
            padding: 18px 35px 12px 35px;
            position: relative;
            z-index: 1;
        }

        /* ── Section Headers ── */
        .section-header {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color:
                {{ $headerColor }}
            ;
            border-bottom: 2px solid
                {{ $headerColor }}
            ;
            padding-bottom: 3px;
            margin-bottom: 8px;
            margin-top: 12px;
        }

        .section-header .icon {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color:
                {{ $headerColor }}
            ;
            border-radius: 2px;
            margin-right: 5px;
            vertical-align: middle;
            position: relative;
            top: -1px;
        }

        /* ── Info Tables (key-value) ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .info-table td {
            padding: 3px 0;
            vertical-align: top;
            border: none;
        }

        .info-label {
            font-size: 8.5px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 90px;
        }

        .info-value {
            font-size: 10px;
            font-weight: 600;
            color: #1a1a2e;
        }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-block;
            background-color:
                {{ $statusColor }}
            ;
            color: #fff;
            font-size: 7.5px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 3px;
        }

        /* ── Two-column Layout ── */
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
        }

        .two-col-table>tbody>tr>td {
            width: 50%;
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .two-col-table>tbody>tr>td:first-child {
            padding-right: 14px;
        }

        .two-col-table>tbody>tr>td:last-child {
            padding-left: 14px;
        }

        /* ── Financial Table ── */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .financial-table thead th {
            background-color:
                {{ $headerColor }}
            ;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 6px 10px;
            text-align: left;
        }

        .financial-table thead th:last-child {
            text-align: right;
        }

        .financial-table tbody td {
            padding: 5px 10px;
            font-size: 9.5px;
            border-bottom: 1px solid #e8e8f0;
        }

        .financial-table tbody td:last-child {
            text-align: right;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .financial-table tbody tr:nth-child(even) {
            background-color: #f8f9ff;
        }

        .financial-total {
            background-color:
                {{ $headerColor }}
                12 !important;
            border-top: 2px solid
                {{ $headerColor }}
            ;
        }

        .financial-total td {
            font-weight: 800 !important;
            font-size: 10px !important;
            color:
                {{ $headerColor }}
            ;
        }

        /* ── Terms & Conditions ── */
        .terms-box {
            background-color: #f5f6fa;
            border: 1px solid #e0e2ef;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 4px;
            font-size: 8px;
            line-height: 1.5;
            color: #444;
        }

        .terms-box ol {
            padding-left: 14px;
            margin: 0;
        }

        .terms-box ol li {
            margin-bottom: 2px;
        }

        /* ── Notes box ── */
        .notes-box {
            background-color: #fffde7;
            border: 1px solid #f0e68c;
            border-left: 3px solid #fbc02d;
            border-radius: 3px;
            padding: 6px 10px;
            margin-top: 4px;
            font-size: 8.5px;
            color: #5d4037;
            font-style: italic;
        }

        /* ── Legal Declaration ── */
        .legal-box {
            background-color: #fff3e0;
            border: 1px solid #ffe0b2;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 10px;
            font-size: 7.5px;
            line-height: 1.55;
            color: #4e342e;
        }

        .legal-box strong {
            color: #bf360c;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Signature Section ── */
        .signature-header {
            text-align: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color:
                {{ $headerColor }}
            ;
            margin-top: 12px;
            margin-bottom: 8px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .sig-block {
            border: 1px solid #d0d4e8;
            border-radius: 4px;
            padding: 10px 12px;
            background-color: #fcfcff;
            margin: 0 4px;
        }

        .sig-role {
            font-size: 8px;
            font-weight: 700;
            color:
                {{ $headerColor }}
            ;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .sig-name {
            font-size: 10px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .sig-line {
            border-bottom: 1px solid #999;
            height: 28px;
            margin-bottom: 3px;
            position: relative;
        }

        .sig-line img {
            position: absolute;
            bottom: 2px;
            left: 0;
            max-height: 30px;
            max-width: 100px;
        }

        .sig-date {
            font-size: 7px;
            color: #888;
        }

        /* ── Company Stamp ── */
        .stamp-area {
            text-align: center;
            margin-top: 6px;
            padding: 4px;
        }

        .stamp-area img {
            max-height: 55px;
            opacity: 0.8;
        }

        /* ── Footer ── */
        .footer-band {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color:
                {{ $headerColor }}
            ;
            color: rgba(255, 255, 255, 0.75);
            padding: 6px 35px;
            font-size: 7px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .footer-left {
            text-align: left;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid #e0e2ef;
            margin: 8px 0;
        }

        /* ── Confidential watermark ── */
        .confidential-mark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 80px;
            font-weight: 900;
            color:
                {{ $headerColor }}
            ;
            opacity: 0.03;
            letter-spacing: 20px;
            text-transform: uppercase;
            z-index: 0;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    <div class="page">

        {{-- ── Background watermark ── --}}
        @if($imageData['lease_background'] ?? null)
            <div class="bg-watermark">
                <img src="{{ $imageData['lease_background'] }}" alt="">
            </div>
        @endif

        {{-- ── Confidential watermark text ── --}}
        @if($lease->status === 'draft')
            <div class="confidential-mark">DRAFT</div>
        @endif

        {{-- ══════════════════════════════════════════════
        HEADER BAND
        ══════════════════════════════════════════════ --}}
        <div class="header-band">
            <table class="header-table">
                <tr>
                    <td class="header-logo">
                        @if($imageData['logo'] ?? null)
                            <img src="{{ $imageData['logo'] }}" alt="Logo">
                        @endif
                    </td>
                    <td class="header-title">
                        <h1>Lease Agreement</h1>
                        <div class="company-name">{{ $companyName }}</div>
                    </td>
                    <td class="header-meta">
                        <div class="contract-badge">{{ $contractNo }}</div>
                        <div class="header-date">{{ $issueDate }}</div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="accent-stripe"></div>

        {{-- ══════════════════════════════════════════════
        CONTENT AREA
        ══════════════════════════════════════════════ --}}
        <div class="content">

            {{-- ── ROW 1: Lease Info + Tenant Info ── --}}
            <table class="two-col-table">
                <tr>
                    {{-- LEFT: Lease Information --}}
                    <td>
                        <div class="section-header">
                            <span class="icon"></span>Lease Information
                        </div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Contract No.:</td>
                                <td class="info-value">{{ $contractNo }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Issue Date:</td>
                                <td class="info-value">{{ $issueDate }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Start Date:</td>
                                <td class="info-value">{{ $startDate }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">End Date:</td>
                                <td class="info-value">{{ $endDate }} <span
                                        style="font-size:7px;color:#888;">({{ $duration }})</span></td>
                            </tr>
                            <tr>
                                <td class="info-label">Status:</td>
                                <td class="info-value"><span class="status-badge">{{ $statusLabel }}</span></td>
                            </tr>
                            <tr>
                                <td class="info-label">Pay Day:</td>
                                <td class="info-value">{{ $payDayLabel }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Frequency:</td>
                                <td class="info-value">{{ $frequency }}</td>
                            </tr>
                        </table>
                    </td>

                    {{-- RIGHT: Tenant Information --}}
                    <td>
                        <div class="section-header">
                            <span class="icon"></span>Tenant Information
                        </div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Full Name:</td>
                                <td class="info-value">{{ $tenantName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Email:</td>
                                <td class="info-value">{{ $tenantEmail }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Phone:</td>
                                <td class="info-value">{{ $tenantPhone }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">{{ $tenantIdType }}:</td>
                                <td class="info-value">{{ $tenantIdNum }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Employer:</td>
                                <td class="info-value">{{ $employer }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Occupants:</td>
                                <td class="info-value">{{ $occupants }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ── ROW 2: Property Details + Financial Terms ── --}}
            <table class="two-col-table">
                <tr>
                    {{-- LEFT: Property Details --}}
                    <td>
                        <div class="section-header">
                            <span class="icon"></span>Property Details
                        </div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Property:</td>
                                <td class="info-value">{{ $propertyName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Unit No.:</td>
                                <td class="info-value">{{ $unitNumber }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Unit Type:</td>
                                <td class="info-value">{{ $unitType }}</td>
                            </tr>
                            @if($address)
                                <tr>
                                    <td class="info-label">Address:</td>
                                    <td class="info-value">{{ $address }}</td>
                                </tr>
                            @endif
                            @if($lease->unit && $lease->unit->bedrooms)
                                <tr>
                                    <td class="info-label">Bedrooms:</td>
                                    <td class="info-value">{{ $lease->unit->bedrooms }}</td>
                                </tr>
                            @endif
                            @if($lease->unit && $lease->unit->bathrooms)
                                <tr>
                                    <td class="info-label">Bathrooms:</td>
                                    <td class="info-value">{{ $lease->unit->bathrooms }}</td>
                                </tr>
                            @endif
                            @if($lease->unit && $lease->unit->sqft)
                                <tr>
                                    <td class="info-label">Area:</td>
                                    <td class="info-value">{{ number_format($lease->unit->sqft) }} sq ft</td>
                                </tr>
                            @endif
                        </table>
                    </td>

                    {{-- RIGHT: Financial Terms --}}
                    <td>
                        <div class="section-header">
                            <span class="icon"></span>Financial Terms
                        </div>
                        <table class="financial-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Rent Amount</td>
                                    <td>${{ $rentAmount }}</td>
                                </tr>
                                <tr>
                                    <td>Security Deposit</td>
                                    <td>${{ $depositAmount }}</td>
                                </tr>
                                <tr>
                                    <td>{{ $frequency }} Installment</td>
                                    <td>${{ $installmentAmt }}</td>
                                </tr>
                                <tr>
                                    <td>No. of Installments</td>
                                    <td>{{ $numInstallments }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ── NOTES ── --}}
            @if($notes)
                <div class="section-header" style="margin-top: 10px;">
                    <span class="icon"></span>Notes
                </div>
                <div class="notes-box">{{ $notes }}</div>
            @endif

            {{-- ── SPECIAL TERMS ── --}}
            @if($specialTerms)
                <div class="section-header" style="margin-top: 10px;">
                    <span class="icon"></span>Special Terms
                </div>
                <div class="terms-box">{!! nl2br(e($specialTerms)) !!}</div>
            @endif

            {{-- ── TERMS & CONDITIONS (from company settings) ── --}}
            @if($leaseTerms)
                <div class="section-header" style="margin-top: 10px;">
                    <span class="icon"></span>Terms &amp; Conditions
                </div>
                <div class="terms-box">{!! nl2br(e($leaseTerms)) !!}</div>
            @else
                <div class="section-header" style="margin-top: 10px;">
                    <span class="icon"></span>Terms &amp; Conditions
                </div>
                <div class="terms-box">
                    <ol>
                        <li>The Tenant agrees to pay rent on the {{ $payDayLabel }} during the term of this lease.</li>
                        <li>A security deposit of ${{ $depositAmount }} is required and will be held by the Landlord for the
                            duration of the lease.</li>
                        <li>The Tenant shall not sublease, assign, or transfer the premises without the prior written
                            consent of the Landlord.</li>
                        <li>The Tenant shall maintain the premises in a clean and habitable condition and promptly report
                            any maintenance issues.</li>
                        <li>Either party may terminate this agreement with written notice as prescribed by applicable
                            tenancy law.</li>
                        <li>Late payments may incur additional fees as determined by the Landlord's policies.</li>
                        <li>The Tenant shall comply with all applicable local, state, and federal laws and regulations.</li>
                        <li>This lease shall be governed by the laws of the jurisdiction in which the property is located.
                        </li>
                    </ol>
                </div>
            @endif

            {{-- ── LEGAL DECLARATION ── --}}
            <div class="legal-box">
                <strong>Legal Declaration:</strong>
                Both parties acknowledge that they have read, understood, and agree to all terms and conditions set
                forth in this lease agreement.
                This contract is legally binding upon execution by both parties and shall be governed by the applicable
                laws and regulations.
                Any disputes arising from this agreement shall be resolved through proper legal channels.
                @if($taxId)
                    <br>Company Tax ID: {{ $taxId }}
                @endif
                @if($regNo)
                    &nbsp;|&nbsp; Registration No.: {{ $regNo }}
                @endif
            </div>

            {{-- ══════════════════════════════════════════════
            SIGNATURES
            ══════════════════════════════════════════════ --}}
            <div class="signature-header">✦&nbsp;&nbsp;Signatures and Authorization&nbsp;&nbsp;✦</div>

            <table class="signature-table">
                <tr>
                    {{-- Landlord / Company signature --}}
                    <td>
                        <div class="sig-block">
                            <div class="sig-role">Landlord / Property Manager</div>
                            <div class="sig-name">{{ $companyName }}</div>
                            <div class="sig-line">
                                @if($imageData['signature'] ?? null)
                                    <img src="{{ $imageData['signature'] }}" alt="Signature">
                                @endif
                            </div>
                            <div class="sig-date">Date: {{ $issueDate }}</div>
                        </div>
                    </td>

                    {{-- Tenant signature --}}
                    <td>
                        <div class="sig-block">
                            <div class="sig-role">Tenant</div>
                            <div class="sig-name">{{ $tenantName }}</div>
                            <div class="sig-line">
                                {{-- Tenant signs physically --}}
                            </div>
                            <div class="sig-date">Date: ___________________</div>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- ── Company Stamp ── --}}
            @if($settings->show_company_stamp && ($imageData['logo'] ?? null))
                <div class="stamp-area">
                    <img src="{{ $imageData['logo'] }}" alt="Company Stamp">
                </div>
            @endif

        </div>{{-- /content --}}

        {{-- ══════════════════════════════════════════════
        FOOTER
        ══════════════════════════════════════════════ --}}
        <div class="footer-band">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        @if($companyAddr){{ $companyAddr }}@endif
                        @if($companyPhone)&nbsp;&nbsp;|&nbsp;&nbsp;{{ $companyPhone }}@endif
                    </td>
                    <td class="footer-center">
                        @if($footerText)
                            {{ $footerText }}
                        @else
                            This document is confidential and intended solely for the parties named herein.
                        @endif
                    </td>
                    <td class="footer-right">
                        @if($companyEmail){{ $companyEmail }}@endif
                        @if($companyWeb)&nbsp;&nbsp;|&nbsp;&nbsp;{{ $companyWeb }}@endif
                    </td>
                </tr>
            </table>
        </div>

    </div>{{-- /page --}}

</body>

</html>