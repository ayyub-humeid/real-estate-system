<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report</title>
    <style>
        body { font-family: 'Arial', sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .stats-container { width: 100%; margin-bottom: 30px; }
        .stat-card { width: 23%; display: inline-block; padding: 15px; border: 1px solid #eee; text-align: center; background: #f9f9f9; }
        .stat-label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .stat-value { font-size: 18px; font-weight: bold; color: #111; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #eee; padding: 10px; text-align: left; }
        th { background-color: #f5f5f5; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #aaa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i') }}</p>
        @if($property)
            <p>Property: <strong>{{ $property->name }}</strong></p>
        @else
            <p>All Properties</p>
        @endif
        @if($startDate || $endDate)
            <p>Period: {{ $startDate ?? 'Start' }} to {{ $endDate ?? 'End' }}</p>
        @endif
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">{{ number_format($revenue, 2) }} JOD</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value">{{ number_format($expenses, 2) }} JOD</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Net Profit</div>
            <div class="stat-value">{{ number_format($revenue - $expenses, 2) }} JOD</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Occupancy Rate</div>
            <div class="stat-value">{{ number_format($occupancy, 1) }}%</div>
        </div>
    </div>

    <h3>Financial Summary</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Collected Payments</td>
                <td>{{ number_format($revenue, 2) }} JOD</td>
            </tr>
            <tr>
                <td>Paid Expenses</td>
                <td>{{ number_format($expenses, 2) }} JOD</td>
            </tr>
            <tr style="font-weight: bold; background: #f0f0f0;">
                <td>Net Cash Flow</td>
                <td>{{ number_format($revenue - $expenses, 2) }} JOD</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Real Estate Management System - Confidential
    </div>
</body>
</html>
