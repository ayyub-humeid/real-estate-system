<?php
// app/Filament/Resources/TenantResource/Pages/ViewTenant.php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    // 🔥 PERFORMANCE: Eager load all relationships
    // 🔥 PERFORMANCE: Eager load all relationships
    public function mount(int | string $record): void
    {
        parent::mount($record);

        // ✅ Optimized: Load only what we need
        $this->record->load([
            'user:id,name,email,phone,company_id',    // User basic info
            'company:id,name',                         // ✅ DIRECT company relationship
            'leases.unit.property', 
            'payments' => function($query) {
                $query->where('payments.status', 'paid'); // Only load paid payments for the total
            },
                              // Lease details
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil'),
            
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Delete Tenant')
                ->modalDescription('Are you sure you want to delete this tenant? This will also delete their user account.')
                ->successNotificationTitle('Tenant deleted successfully'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // 🔥 SECTION 1: Tenant Header (Photo + Key Info)
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\Split::make([
                            // Left: Avatar
                            Infolists\Components\Group::make([
                                Infolists\Components\ImageEntry::make('avatar')
                                    ->circular()
                                    ->size(120)
                                    ->defaultImageUrl(fn($record) => 
                                        "https://ui-avatars.com/api/?name=" . urlencode($record->user->name) . 
                                        "&size=200&background=0D8ABC&color=fff&bold=true"
                                    ),
                            ]),
                            
                            // Right: Basic Info
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Full Name')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight('bold')
                                    ->icon('heroicon-m-user')
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('user.email')
                                    ->label('Email')
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->copyMessage('Email copied!')
                                    ->copyMessageDuration(1500),

                                Infolists\Components\TextEntry::make('user.phone')
                                    ->label('Phone')
                                    ->icon('heroicon-m-phone')
                                    ->placeholder('—')
                                    ->copyable(),

                                Infolists\Components\IconEntry::make('status')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger')
                                    ->state(fn($record) => $record->status === 'active'),
                            ])->columns(2),
                        ])->from('md'),
                    ])
                    ->columnSpanFull(),

                // 🔥 SECTION 2: Statistics Cards
                Infolists\Components\Section::make('Tenancy Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            // Card 1: Total Leases
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('leases_count')
                                    ->label('Total Leases')
                                    ->state(fn($record) => $record->leases_count ?? 0)
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('info')
                                    ->icon('heroicon-m-document-text'),
                                
                                Infolists\Components\TextEntry::make('leases_label')
                                    ->label('')
                                    ->state('Rental contracts')
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800']),

                            // Card 2: Active Leases
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('active_leases_count')
                                    ->label('Active Leases')
                                    ->state(fn($record) => $record->active_leases_count ?? 0)
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('success')
                                    ->icon('heroicon-m-home'),
                                
                                Infolists\Components\TextEntry::make('active_label')
                                    ->label('')
                                    ->state('Currently renting')
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800']),

                            // Card 3: Total Paid
             Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('total_paid')
                                    ->label('Total Payments Received')
                                    ->state(function ($record) {
                                        // ✅ Use query aggregation, NOT collection methods
                                        // This runs a single SQL query instead of loading all payments
                                        return $record->payments()
                                            ->where('payments.status', 'paid')
                                            ->sum('payments.paid_amount') ?? 0;
                                    })
                                    ->money('USD')
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('success')
                                    ->icon('heroicon-m-banknotes'), 
                                             
                                Infolists\Components\TextEntry::make('paid_label')
                                    ->label('')
                                    ->state('All-time payments')
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800']),

                            // Card 4: Member Since
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Member Since')
                                    ->since()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('gray')
                                    ->icon('heroicon-m-calendar')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('created_date')
                                    ->label('')
                                    ->state(fn($record) => $record->created_at->format('M d, Y'))
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-100 dark:border-gray-700']),
                        ])->from('md'),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                // 🔥 SECTION 3: Personal Information
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('company.name')
                            ->label('Company')
                            ->icon('heroicon-m-building-office')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('move_in_date')
                            ->label('Move-in Date')
                            ->date()
                            ->placeholder('—')
                            ->icon('heroicon-m-calendar-days'),

                        Infolists\Components\TextEntry::make('number_of_occupants')
                            ->label('Number of Occupants')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-users'),

                        Infolists\Components\IconEntry::make('has_pets')
                            ->label('Has Pets')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('warning')
                            ->falseColor('gray'),

                        Infolists\Components\TextEntry::make('pet_details')
                            ->label('Pet Details')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible(fn($record) => $record->has_pets),
                    ])
                    ->columns(4)
                    ->collapsible(),

                // 🔥 SECTION 4: Employment Information
                Infolists\Components\Section::make('Employment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('employer_name')
                            ->label('Employer')
                            ->icon('heroicon-m-building-office-2')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('job_title')
                            ->label('Job Title')
                            ->icon('heroicon-m-briefcase')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('monthly_income')
                            ->label('Monthly Income')
                            ->money('USD')
                            ->icon('heroicon-m-banknotes')
                            ->badge()
                            ->color('success')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('employment_start_date')
                            ->label('Employment Start')
                            ->date()
                            ->icon('heroicon-m-calendar')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('employer_phone')
                            ->label('Employer Phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('employer_address')
                            ->label('Employer Address')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 5: Emergency Contact
                Infolists\Components\Section::make('Emergency Contact')
                    ->schema([
                        Infolists\Components\TextEntry::make('emergency_contact_name')
                            ->label('Contact Name')
                            ->icon('heroicon-m-user')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('emergency_contact_phone')
                            ->label('Contact Phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('emergency_contact_relationship')
                            ->label('Relationship')
                            ->icon('heroicon-m-heart')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 6: Previous Address
                Infolists\Components\Section::make('Previous Rental History')
                    ->schema([
                        Infolists\Components\TextEntry::make('previous_address')
                            ->label('Previous Address')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('previous_landlord_name')
                            ->label('Previous Landlord')
                            ->icon('heroicon-m-user')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('previous_landlord_phone')
                            ->label('Landlord Phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('previous_tenancy_start')
                            ->label('Tenancy Start')
                            ->date()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('previous_tenancy_end')
                            ->label('Tenancy End')
                            ->date()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('previous_duration')
                            ->label('Duration')
                            ->state(function($record) {
                                if (!$record->previous_tenancy_start || !$record->previous_tenancy_end) {
                                    return '—';
                                }
                                return $record->previous_tenancy_start->diffForHumans($record->previous_tenancy_end, true);
                            })
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 7: Identification
                Infolists\Components\Section::make('Identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('id_type')
                            ->label('ID Type')
                            ->badge()
                            ->formatStateUsing(fn($state) => str_replace('_', ' ', ucwords($state, '_')))
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('id_number')
                            ->label('ID Number')
                            ->placeholder('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('id_expiry_date')
                            ->label('Expiry Date')
                            ->date()
                            ->placeholder('—')
                            ->color(fn($record) => 
                                $record->id_expiry_date && $record->id_expiry_date->isPast() 
                                    ? 'danger' 
                                    : 'success'
                            ),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 8: References
                Infolists\Components\Section::make('References')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('references')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Name')
                                    ->icon('heroicon-m-user')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('relationship')
                                    ->label('Relationship')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Phone')
                                    ->icon('heroicon-m-phone')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->placeholder('—'),
                            ])
                            ->columns(4)
                            ->grid(1)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => !empty($record->references)),

                // 🔥 SECTION 9: Background Check
                Infolists\Components\Section::make('Background Check')
                    ->schema([
                        Infolists\Components\TextEntry::make('background_check_status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'approved',
                                'danger' => 'rejected',
                                'gray' => 'not_required',
                            ])
                            ->formatStateUsing(fn($state) => ucfirst($state)),

                        Infolists\Components\TextEntry::make('background_check_date')
                            ->label('Check Date')
                            ->date()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('background_check_notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 10: Internal Notes
                Infolists\Components\Section::make('Internal Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('')
                            ->placeholder('No notes added')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => filled($record->notes)),
            ]);
    }

    // 🔥 Enable tabs for relations (leases, payments, documents)
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
