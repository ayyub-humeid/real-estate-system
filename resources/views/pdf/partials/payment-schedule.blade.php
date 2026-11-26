@if ($paymentPlan)
    @unless ($paymentPlan['issued'])
        <p class="notice">{{ __('contract_domain.pdf.schedule_not_issued') }}</p>
    @endunless
    @unless ($paymentPlan['reconciles'])
        <p class="notice">{{ __('contract_domain.pdf.schedule_mismatch') }}</p>
    @endunless
    <table class="schedule">
        <thead>
            <tr>
                <th class="num" style="width:8%">#</th>
                <th style="width:30%">{{ __('contract_domain.pdf.payment_kind') }}</th>
                <th class="num" style="width:27%">{{ __('contract_domain.pdf.payment_due') }}</th>
                <th class="num" style="width:35%">{{ __('contract_domain.pdf.payment_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paymentPlan['installments'] as $installment)
                <tr>
                    <td class="num">{{ $installment->sequence }}</td>
                    <td>{{ $installment->kind?->getLabel() ?? '—' }}</td>
                    <td class="num">{{ $installment->due_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $installment->amount, 2) }} {{ $paymentPlan['currency'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">{{ __('contract_domain.pdf.payment_total') }}</td>
                <td class="num">{{ number_format((float) $paymentPlan['total'], 2) }} {{ $paymentPlan['currency'] }}</td>
            </tr>
        </tfoot>
    </table>
@else
    <p class="notice">{{ __('contract_domain.pdf.schedule_missing') }}</p>
@endif
