<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 15mm 15mm 20mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            color: #1f2937;
            font-size: 10pt;
            line-height: 1.7;
        }

        .watermark {
            position: fixed;
            top: 46%;
            left: 23%;
            font-size: 28pt;
            color: #e7c58a;
            opacity: .03;
            transform: rotate(-28deg);
        }

        .header {
            width: 100%;
            background: #ffffff;
            border-bottom: 3px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            padding-bottom: 10px;
            margin-bottom: 13px;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            max-width: 92px;
            max-height: 48px;
        }

        .logo-fallback {
            width: 44px;
            height: 44px;
            line-height: 44px;
            text-align: center;
            border: 1px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            color: {{ $settings?->contract_header_color ?? '#b7843b' }};
            font-size: 17pt;
            font-weight: bold;
        }

        .company {
            font-size: 14pt;
            font-weight: bold;
            color: #111827;
        }

        .reference {
            color: #6b7280;
            font-size: 8.5pt;
        }

        .company-meta {
            color: #64748b;
            font-size: 7.5pt;
            margin-top: 2px;
            line-height: 1.45;
        }

        .contract-kind {
            color: {{ $settings?->contract_header_color ?? '#b7843b' }};
            font-size: 10pt;
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-size: 19pt;
            font-weight: bold;
            margin: 0;
            color: #111827;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 9pt;
            margin: 4px 0 0;
        }

        .identity {
            width: 100%;
            background: #ffffff;
            border: 1px solid #dbe2ea;
            border-bottom: 2px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            margin: 0 0 12px;
        }

        .identity td {
            padding: 9px 12px;
            text-align: center;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin: 0 0 14px;
        }

        .summary td {
            width: 33.333%;
            background: #f5f7fa;
            border: 1px solid #dbe2ea;
            border-top: 2px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            padding: 9px 10px;
            vertical-align: top;
        }

        .field-label {
            display: block;
            color: #64748b;
            font-size: 8pt;
            margin-bottom: 4px;
        }

        .field-value {
            display: block;
            font-weight: bold;
            color: #172033;
            font-size: 10.25pt;
            line-height: 1.45;
        }

        .amount-value {
            direction: ltr;
            text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};
        }

        .section-title {
            font-size: 12.5pt;
            font-weight: bold;
            color: #172033;
            background: #f2f5f8;
            border-right: 4px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            border-bottom: 1px solid #dbe2ea;
            padding: 7px 10px;
            margin: 17px 0 9px;
        }

        .legal-body {
            margin: 8px 0 16px;
            padding: 4px 12px;
            border-right: 2px solid #d7b56f;
        }

        .legal-body h1,
        .legal-body h2,
        .legal-body h3 {
            color: #182230;
        }

        .term {
            border: 1px solid #dbe2ea;
            border-right: 3px solid #d7b56f;
            background: #fafbfc;
            padding: 10px 12px;
            margin: 8px 0;
            page-break-inside: avoid;
        }

        .term h3 {
            margin: 0 0 5px;
            font-size: 10.75pt;
            color: #172033;
        }

        .term p {
            margin: 0;
        }

        .signatures {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 20px;
        }

        .signature {
            width: 50%;
            border-top: 2px solid {{ $settings?->contract_header_color ?? '#b7843b' }};
            padding: 10px 5px;
            vertical-align: top;
        }

        .signature img {
            max-width: 115px;
            max-height: 55px;
            margin-top: 7px;
        }

        .footer {
            position: fixed;
            bottom: -10mm;
            width: 100%;
            border-top: 1px solid #dbe2ea;
            color: #64748b;
            font-size: 8pt;
            padding-top: 4px;
            text-align: center;
        }

    </style>
</head>

<body>
    @if ($draft)
        <div class="watermark">{{ __('Draft') }} / مسودة</div>
    @endif
    @php
        $companyName = $settings?->company_legal_name ?: $contract->company?->name;
        $companyInitial = \Illuminate\Support\Str::substr($companyName ?: 'M', 0, 1);
        $companyContact = collect([
            $settings?->company_address,
            $settings?->company_phone,
            $settings?->company_email,
            $settings?->website,
        ])->filter()->implode(' · ');
        $companyRegistration = collect([
            $settings?->registration_number ? __('Commercial Registration').': '.$settings->registration_number : null,
            $settings?->tax_id ? __('Tax ID / VAT Number').': '.$settings->tax_id : null,
        ])->filter()->implode(' · ');
    @endphp
    <table class="header">
        <tr>
            <td style="width:18%">
                @if ($images['logo'] ?? null)
                    <img class="logo" src="{{ $images['logo'] }}" alt="">
                @else
                    <div class="logo-fallback">{{ $companyInitial }}</div>
                @endif
            </td>
            <td style="width:82%">
                <div class="company">{{ $companyName }}</div>
                <div class="reference">{{ $contract->contract_number }} · V{{ $contractVersion->version_number }}</div>
                @if ($companyContact)
                    <div class="company-meta">{{ $companyContact }}</div>
                @endif
                @if ($companyRegistration)
                    <div class="company-meta">{{ $companyRegistration }}</div>
                @endif
            </td>
        </tr>
    </table>
    <table class="identity">
        <tr>
            <td>
                <div class="title">{{ __('Contract') }} {{ $contract->contract_number }}</div>
                <p class="subtitle">
                    <span class="contract-kind">{{ $contract->contract_type?->getLabel() }}</span>
                    · {{ $draft ? __('Preview only — this draft has no legal effect.') : __('Contract version') . ' V' . $contractVersion->version_number }}
                </p>
            </td>
        </tr>
    </table>
    <table class="summary">
        <tr>
            <td><div class="field-label">{{ __('Contract type') }}</div><div
                    class="field-value">{{ $contract->contract_type?->getLabel() }}</div></td>
            <td><div class="field-label">{{ __('Agreed amount') }}</div><div
                    class="field-value amount-value">{{ number_format((float) $contractVersion->total_amount, 2) }} {{ $contractVersion->currency }}</div></td>
            <td><div class="field-label">{{ __('Contract target') }}</div><div
                    class="field-value">{{ $contractSummary['unit'] }}</div>
            </td>
        </tr>
        <tr>
            <td><div class="field-label">{{ __('Property') }}</div><div
                    class="field-value">{{ $contractSummary['property'] }}</div></td>
            <td><div class="field-label">{{ __('contract_domain.pdf.project') }}</div><div
                    class="field-value">{{ $contractSummary['project'] }}</div></td>
            <td><div class="field-label">{{ __('Start date') }} / {{ __('End date') }}</div><div
                    class="field-value amount-value">{{ $contractVersion->start_date?->format('Y-m-d') ?? '—' }} —
                    {{ $contractVersion->end_date?->format('Y-m-d') ?? '—' }}</div></td>
        </tr>
    </table>
    <h2 class="section-title">{{ __('Contract parties') }}</h2>
    @php($partyRoles = ['buyer' => __('Buyer'), 'seller' => __('Seller'), 'landlord' => __('Landlord'), 'tenant' => __('Tenant'), 'guarantor' => __('Guarantor')])
    <table class="summary">
        <tr>
            @foreach ($contractVersion->parties as $party)
                <td><div class="field-label">{{ __('Role') }}: {{ $partyRoles[$party->role] ?? $party->role }}</div><div
                        class="field-value">{{ $party->snapshot['name'] ?? ($party->party?->name ?? '—') }}</div></td>
            @endforeach
        </tr>
    </table>
    <h2 class="section-title">{{ __('Legal document content') }}</h2>
    <div class="legal-body">{!! $body !!}</div>
    <h2 class="section-title">{{ __('Contract terms') }}</h2>
    @forelse($contractVersion->terms as $term)
        <section class="term">
            <h3>{{ $term->title }}</h3>
            <p>{!! nl2br(e($term->text ?? '—')) !!}</p>
        </section>
    @empty<p>—</p>
    @endforelse
    @if (count($signatures))
        <h2 class="section-title">{{ __('Signatures') }}</h2>
        <table class="signatures">
            <tr>
                @foreach ($signatures as $signature)
                    <td class="signature">
                        <strong>{{ $signature->signatory_name }}</strong>
                        <br>
                        <span class="reference">
                            {{ $signature->party?->snapshot['name'] ?? '' }} ·
                            {{ $signature->signed_at?->format('Y-m-d H:i') }}

                        </span>
                        @if ($images['signature_' . $signature->id] ?? null)
                            <br><img src="{{ $images['signature_' . $signature->id] }}" alt="">
                        @endif
                        @if ($signature->signing_method === 'company_authorized' && ($images['stamp_' . $signature->id] ?? null))
                            <br><img src="{{ $images['stamp_' . $signature->id] }}" alt="">
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
    <footer class="footer">
        {{ $settings?->contract_footer_text ?: $contract->contract_number }}
    </footer>
</body>

</html>
