<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\FinancialTrendChart;
use App\Filament\Widgets\OccupancyStats;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialReport extends Page implements HasForms
{
    use HasPageShield, InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'data.')) {
            $this->dispatch('refreshWidgets', filters: $this->data);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->schema([
                        Select::make('property_id')
                            ->label('Property')
                            ->options(Property::pluck('name', 'id'))
                            ->placeholder('All Properties')
                            ->searchable()
                            ->live(),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->live(),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->live(),
                    ])
                    ->columns(3)
                    ->compact(),
            ])
            ->statePath('data');
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $title = 'Financial Report';
    protected static string $view = 'filament.pages.financial-report';

    protected function getHeaderWidgets(): array
    {
        return [
            FinancialOverviewStats::class,
            OccupancyStats::class,
            FinancialTrendChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Download PDF Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToPdf'),
        ];
    }

    public function exportToPdf()
    {
        $propertyId = $this->data['property_id'] ?? null;
        $startDate = $this->data['start_date'] ?? null;
        $endDate = $this->data['end_date'] ?? null;

        // Fetch data for PDF (Scoped by company via trait)
        $revenue = Payment::when($propertyId, fn($q) => $q->whereHas('lease.unit', fn($uq) => $uq->where('property_id', $propertyId)))
            ->when($startDate, fn($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('payment_date', '<=', $endDate))
            ->sum('paid_amount');

        $expenses = Expense::where('status', 'paid')
            ->when($propertyId, fn($q) => $q->where('property_id', $propertyId))
            ->when($startDate, fn($q) => $q->where('expense_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('expense_date', '<=', $endDate))
            ->sum('amount');

        $unitsQuery = Unit::when($propertyId, fn($q) => $q->where('property_id', $propertyId));
        $totalUnits = (clone $unitsQuery)->count();
        $occupiedUnits = (clone $unitsQuery)->where('status', 'occupied')->count();
        $occupancy = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;

        $property = $propertyId ? Property::find($propertyId) : null;

        $pdf = Pdf::loadView('pdf.financial-report', [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'occupancy' => $occupancy,
            'property' => $property,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return response()->streamDownload(fn () => print($pdf->output()), "financial-report-" . now()->format('Y-m-d') . ".pdf");
    }

    protected function getHeaderWidgetsConfig(): array
    {
        return [
            FinancialOverviewStats::class => [
                'filters' => $this->data,
            ],
            OccupancyStats::class => [
                'filters' => $this->data,
            ],
            FinancialTrendChart::class => [
                'filters' => $this->data,
            ],
        ];
    }
}
