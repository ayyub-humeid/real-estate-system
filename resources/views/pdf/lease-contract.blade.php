<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lease Contract - {{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.55;
            color: #1a1a2e;
            background: #fff;
            position: relative;
        }

        /* ── BACKGROUND IMAGE ── */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .bg-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.07;
        }

        /* ── MAIN WRAPPER ── */
        .page-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 0;
        }

        /* ── HEADER BAND ── */
        .header-band {
            background: {{ $settings->lease_header_color ?? '#1e3a5f' }};
            padding: 18px 36px 14px 36px;
            display: block;
            width: 100%;
        }

        .header-inner {
            display: table;
            width: 100%;
        }

        .header-logo-cell {
            display: table-cell;
            width: 90px;
            vertical-align: middle;
        }

        .header-logo-cell img {
            max-width: 80px;
            max-height: 65px;
        }

        .header-text-cell {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 0 10px;
        }

        .header-title {
            font-size: 20pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 9pt;
            color: rgba(255,255,255,0.80);
            margin-top: 3px;
            letter-spacing: 1px;
        }

        .header-meta-cell {
            display: table-cell;
            width: 130px;
            vertical-align: middle;
            text-align: right;
        }

        .contract-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.30);
            border-radius: 4px;
            padding: 6px 10px;
            color: #fff;
            font-size: 8pt;
        }

        .contract-badge .badge-no {
            font-size: 13pt;
            font-weight: bold;
            display: block;
            letter-spacing: 1px;
        }

        /* ── COMPANY INFO STRIP ── */
        .company-strip {
            background: #f0f4fa;
            border-bottom: 2px solid {{ $settings->lease_header_color ?? '#1e3a5f' }};
            padding: 7px 36px;
            font-size: 8pt;
            color: #445;
            text-align: center;
        }

        .company-strip span {
            margin: 0 10px;
        }

        /* ── CONTENT AREA ── */
        .content {
            padding: 16px 36px 0 36px;
        }

        /* ── TWO-COLUMN GRID ── */
        .two-col {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .col-left, .col-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .col-left {
            padding-right: 14px;
        }

        .col-right {
            padding-left: 14px;
            border-left: 1px solid #dce3ef;
        }

        /* ── SECTION ── */
        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 8pt;
            font-weight: bold;
            color: {{ $settings->lease_header_color ?? '#1e3a5f' }};
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 1.5px solid {{ $settings->lease_header_color ?? '#1e3a5f' }};
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        /* ── INFO ROWS ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 0;
            vertical-align: top;
            border: none;
        }

        .info-label {
            font-weight: bold;
            color: #556;
            width: 110px;
            font-size: 8.5pt;
            white-space: nowrap;
        }

        .info-value {
            color: #111;
            font-size: 8.5pt;
        }

        /* ── FINANCIAL TABLE ── */
        .fin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 8.5pt;
        }

        .fin-table th {
            background: {{ $settings->lease_header_color ?? '#1e3a5f' }};
            color: #fff;
            padding: 5px 8px;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
        }

        .fin-table th.right,
        .fin-table td.right {
            text-align: right;
        }

        .fin-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #e8ecf4;
        }

        .fin-table tr:nth-child(even) td {
            background: #f7f9fd;
        }

        .fin-total td {
            font-weight: bold;
            border-top: 2px solid {{ $settings->lease_header_color ?? '#1e3a5f' }};
            background: #eef2fa !important;
        }

        /* ── STATUS BADGE ── */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active   { background: #d1fae5; color: #065f46; }
        .status-draft    { background: #f3f4f6; color: #374151; }
        .status-expired  { background: #fee2e2; color: #991b1b; }
        .status-terminated { background: #fef3c7; color: #92400e; }
        .status-renewed  { background: #dbeafe; color: #1e40af; }

        /* ── DIVIDER ── */
        .divider {
            border: none;
            border-top: 1px solid #dce3ef;
            margin: 10px 0;
        }

        /* ── TERMS BOX ── */
        .terms-box {
            border: 1px solid #dce3ef;
            border-left: 3.5px solid {{ $settings->lease_header_color ?? '#1e3a5f' }};
            background: #f9fafd;
            padding: 8px 12px;
            font-size: 8pt;
            color: #334;
            line-height: 1.5;
            margin-top: 4px;
        }

        /* ── NOTES BOX ── */
        .notes-box {
            border: 1px dashed #c4cfe0;
            background: #fefefe;
            padding: 7px 12px;
            font-size: 8pt;
            color: #445;
            line-height: 1.5;
            margin-top: 4px;
        }

        /* ── FULL-WIDTH SECTION ── */
        .full-width {
            padding: 0 36px;
            margin-bottom: 10px;
        }

        /* ── SIGNATURE SECTION ── */
        .sig-section {
            display: table;
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .sig-cell {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 20px;
        }

        .sig-cell-left {
            border-right: 1px solid #dce3ef;
        }

        .sig-image {
            max-width: 140px;
            max-height: 70px;
            margin-bottom: 4px;
        }

        .sig-line {
            border-top: 1.5px solid #334;
            margin-top: 50px;
            padding-top: 6px;
        }

        .sig-name {
            font-weight: bold;
            font-size: 9pt;
            color: #1a1a2e;
        }

        .sig-role {
            font-size: 7.5pt;
            color: #667;
            margin-top: 2px;
        }

        .sig-date-line {
            margin-top: 6px;
            font-size: 8pt;
            color: #556;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }

        /* ── FOOTER BAND ── */
        .footer-band {
            background: {{ $settings->lease_header_color ?? '#1e3a5f' }};
            margin-top: 12px;
            padding: 8px 36px;
            text-align: center;
            font-size: 7.5pt;
            color: rgba(255,255,255,0.80);
            width: 100%;
            display: block;
        }

        .footer-band .footer-highlight {
            color: #fff;
            font-weight: bold;
        }

        /* ── STAMP PLACEHOLDER ── */
        .stamp-area {
            display: inline-block;
            width: 80px;
            height: 80px;
            border: 2px dashed {{ $settings->lease_header_color ?? '#1e3a5f' }};
            border-radius: 50%;
            opacity: 0.35;
            vertical-align: middle;
            margin-top: 5px;
        }

        /* ── WATERMARK TEXT ── */
        .watermark-text {
            position: fixed;
            top: 38%;
            left: 18%;
            font-size: 68pt;
            font-weight: bold;
            color: {{ $settings->lease_header_color ?? '#1e3a5f' }};
            opacity: 0.035;
            transform: rotate(-30deg);
            z-index: 0;
            letter-spacing: 6px;
        }
    </style>
</head>
<body>

    {{-- ── BACKGROUND IMAGE (dynamic from company settings) ── --}}
    @if($settings && $settings->lease_background)
        <div class="bg-image">
            <img src="{{ public_path('storage/' . $settings->lease_background) }}" alt="">
        </div>
    @endif

    {{-- ── WATERMARK ── --}}
    <div class="watermark-text">LEASE</div>

    <div class="page-wrapper">

        {{-- ══ HEADER BAND ══ --}}
        <div class="header-band">
            <div class="header-inner">
                {{-- Logo --}}
                <div class="header-logo-cell">
                    @if($settings && $settings->logo)
                        <img src="{{ public_path('storage/' . $settings->logo) }}" alt="Logo">
                    @endif
                </div>

                {{-- Title --}}
                <div class="header-text-cell">
                    <div class="header-title">LEASE AGREEMENT</div>
                    <div class="header-subtitle">
                        {{ $settings->company_legal_name ?? ($lease->company->name ?? 'Real Estate Management') }}
                    </div>
                </div>

                {{-- Contract Number --}}
                <div class="header-meta-cell">
                    <div class="contract-badge">
                        <span style="font-size:7pt; opacity:0.8; letter-spacing:0.5px;">CONTRACT NO.</span>
                        <span class="badge-no">#{{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }}</span>
                        <span style="font-size:7pt; opacity:0.8; display:block; margin-top:2px;">
                            {{ now()->format('d M Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ COMPANY INFO STRIP ══ --}}
        <div class="company-strip">
            @if($settings && $settings->company_address)
                <span>{{ $settings->company_address }}</span>
            @endif
            @if($settings && $settings->company_phone)
                <span> | Tel: {{ $settings->company_phone }}</span>
            @endif
            @if($settings && $settings->company_email)
                <span> | {{ $settings->company_email }}</span>
            @endif
            @if($settings && $settings->tax_id)
                <span> | Tax ID: {{ $settings->tax_id }}</span>
            @endif
            @if($settings && $settings->registration_number)
                <span> | Reg: {{ $settings->registration_number }}</span>
            @endif
        </div>

        {{-- ══ MAIN CONTENT ══ --}}
        <div class="content">

            {{-- ── TWO-COLUMN: Lease Info  +  Tenant Info ── --}}
            <div class="two-col">

                {{-- LEFT: Lease & Property Details --}}
                <div class="col-left">

                    {{-- LEASE INFORMATION --}}
                    <div class="section">
                        <div class="section-title">Lease Information</div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Contract No.:</td>
                                <td class="info-value"><strong>#{{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }}</strong></td>
                            </tr>
                            <tr>
                                <td class="info-label">Issue Date:</td>
                                <td class="info-value">{{ $lease->created_at->format('d F Y') }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Start Date:</td>
                                <td class="info-value">{{ $lease->start_date->format('d F Y') }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">End Date:</td>
                                <td class="info-value">
                                    {{ $lease->end_date ? $lease->end_date->format('d F Y') : 'Open-ended' }}
                                    @if($lease->end_date)
                                        @php $months = $lease->start_date->diffInMonths($lease->end_date); @endphp
                                        ({{ $months }} month{{ $months != 1 ? 's' : '' }})
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="info-label">Status:</td>
                                <td class="info-value">
                                    <span class="status-badge status-{{ $lease->status }}">{{ $lease->status }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="info-label">Pay Day:</td>
                                <td class="info-value">{{ $lease->payment_day }}{{ in_array($lease->payment_day, [1,21,31]) ? 'st' : (in_array($lease->payment_day, [2,22]) ? 'nd' : (in_array($lease->payment_day, [3,23]) ? 'rd' : 'th')) }} of each month</td>
                            </tr>
                            <tr>
                                <td class="info-label">Frequency:</td>
                                <td class="info-value">{{ ucwords(str_replace('_', ' ', $lease->payment_frequency)) }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- PROPERTY DETAILS --}}
                    <div class="section">
                        <div class="section-title">Property Details</div>
                        <table class="info-table">
                            @if($lease->unit)
                                <tr>
                                    <td class="info-label">Property:</td>
                                    <td class="info-value">{{ optional($lease->unit->property)->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Unit No.:</td>
                                    <td class="info-value"><strong>{{ $lease->unit->unit_number }}</strong></td>
                                </tr>
                                @if($lease->unit->type)
                                <tr>
                                    <td class="info-label">Unit Type:</td>
                                    <td class="info-value">{{ ucfirst($lease->unit->type) }}</td>
                                </tr>
                                @endif
                                @if(optional($lease->unit->property)->address)
                                <tr>
                                    <td class="info-label">Address:</td>
                                    <td class="info-value">{{ $lease->unit->property->address }}</td>
                                </tr>
                                @endif
                            @elseif($lease->property)
                                <tr>
                                    <td class="info-label">Property:</td>
                                    <td class="info-value"><strong>{{ $lease->property->name }}</strong> (Whole)</td>
                                </tr>
                                @if($lease->property->address)
                                <tr>
                                    <td class="info-label">Address:</td>
                                    <td class="info-value">{{ $lease->property->address }}</td>
                                </tr>
                                @endif
                            @endif
                        </table>
                    </div>

                </div>

                {{-- RIGHT: Tenant + Financial --}}
                <div class="col-right">

                    {{-- TENANT INFORMATION --}}
                    <div class="section">
                        <div class="section-title">Tenant Information</div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Full Name:</td>
                                <td class="info-value"><strong>{{ optional($lease->tenant->user)->name ?? 'N/A' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="info-label">Email:</td>
                                <td class="info-value">{{ optional($lease->tenant->user)->email ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Phone:</td>
                                <td class="info-value">{{ optional($lease->tenant->user)->phone ?? 'N/A' }}</td>
                            </tr>
                            @if($lease->tenant->id_type && $lease->tenant->id_number)
                            <tr>
                                <td class="info-label">ID ({{ $lease->tenant->id_type }}):</td>
                                <td class="info-value">{{ $lease->tenant->id_number }}</td>
                            </tr>
                            @endif
                            @if($lease->tenant->employer_name)
                            <tr>
                                <td class="info-label">Employer:</td>
                                <td class="info-value">{{ $lease->tenant->employer_name }}</td>
                            </tr>
                            @endif
                            @if($lease->tenant->number_of_occupants)
                            <tr>
                                <td class="info-label">Occupants:</td>
                                <td class="info-value">{{ $lease->tenant->number_of_occupants }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>

                    {{-- FINANCIAL TERMS --}}
                    <div class="section">
                        <div class="section-title">Financial Terms</div>
                        <table class="fin-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Rent Amount</td>
                                    <td class="right"><strong>${{ number_format($lease->rent_amount, 2) }}</strong></td>
                                </tr>
                                @if((float)$lease->deposit_amount > 0)
                                <tr>
                                    <td>Security Deposit</td>
                                    <td class="right">${{ number_format($lease->deposit_amount, 2) }}</td>
                                </tr>
                                @endif
                                @php
                                    $freqMap = ['monthly'=>1,'quarterly'=>3,'semi_annually'=>6,'yearly'=>12];
                                    $freqMonths = $freqMap[$lease->payment_frequency] ?? 1;
                                    $totalMonths = $lease->end_date ? (int)$lease->start_date->diffInMonths($lease->end_date) : null;
                                    $installments = $totalMonths ? (int)ceil($totalMonths / $freqMonths) : null;
                                    $installmentAmt = ($installments && $installments > 0) ? round($lease->rent_amount / $installments, 2) : null;
                                @endphp
                                @if($installments)
                                <tr>
                                    <td>{{ ucwords(str_replace('_', ' ', $lease->payment_frequency)) }} Installment</td>
                                    <td class="right">${{ number_format($installmentAmt, 2) }}</td>
                                </tr>
                                <tr class="fin-total">
                                    <td>No. of Installments</td>
                                    <td class="right">{{ $installments }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>{{-- end two-col --}}

        </div>{{-- end content --}}

        {{-- ══ TERMS & CONDITIONS (full width) ══ --}}
        @if(($settings && $settings->lease_terms) || $lease->special_terms || $lease->notes)
        <div class="full-width">
            <hr class="divider">

            @if($settings && $settings->lease_terms)
            <div class="section">
                <div class="section-title" style="margin-left:0">Terms and Conditions</div>
                <div class="terms-box">
                    {!! nl2br(e($settings->lease_terms)) !!}
                </div>
            </div>
            @endif

            @if($lease->special_terms)
            <div class="section" style="margin-top:6px">
                <div class="section-title" style="margin-left:0">Special Terms</div>
                <div class="terms-box">
                    {!! nl2br(e($lease->special_terms)) !!}
                </div>
            </div>
            @endif

            @if($lease->notes)
            <div class="section" style="margin-top:6px">
                <div class="section-title" style="margin-left:0">Notes</div>
                <div class="notes-box">
                    {!! nl2br(e($lease->notes)) !!}
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- ══ SIGNATURE SECTION ══ --}}
        <div class="full-width">
            <hr class="divider">
            <div class="section-title" style="text-align:center; border:none; margin-bottom:4px;">Signatures and Authorization</div>

            <div class="sig-section">
                {{-- Landlord / Company --}}
                <div class="sig-cell sig-cell-left">
                    @if($settings && $settings->signature)
                        <div>
                            <img src="{{ public_path('storage/' . $settings->signature) }}" class="sig-image" alt="Company Signature">
                        </div>
                    @else
                        <div style="height:70px;"></div>
                    @endif

                    @if($settings && $settings->show_company_stamp)
                        <div class="stamp-area"></div>
                    @endif

                    <div class="sig-line">
                        <div class="sig-name">{{ $settings->company_legal_name ?? ($lease->company->name ?? '') }}</div>
                        <div class="sig-role">Landlord / Authorized Representative</div>
                    </div>
                    <div class="sig-date-line">Date: ____________________</div>
                </div>

                {{-- Tenant --}}
                <div class="sig-cell">
                    <div style="height:70px;"></div>

                    <div class="sig-line">
                        <div class="sig-name">{{ optional($lease->tenant->user)->name ?? 'Tenant' }}</div>
                        <div class="sig-role">Tenant</div>
                    </div>
                    <div class="sig-date-line">Date: ____________________</div>
                </div>
            </div>
        </div>

        {{-- ══ FOOTER BAND ══ --}}
        <div class="footer-band">
            @if($settings && $settings->lease_footer_text)
                {{ $settings->lease_footer_text }}
                &nbsp;|&nbsp;
            @else
                This is a legally binding agreement. Please read carefully before signing.
                &nbsp;|&nbsp;
            @endif
            <span class="footer-highlight">Contract #{{ str_pad($lease->id, 6, '0', STR_PAD_LEFT) }}</span>
            &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, H:i') }}
            @if($settings && $settings->website)
                &nbsp;|&nbsp; {{ $settings->website }}
            @endif
        </div>

    </div>{{-- end page-wrapper --}}
</body>
</html>