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
            // 'payments as all_payments'

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
                ->modalHeading(__('tenant.messages.delete_heading'))
                ->modalDescription(__('tenant.messages.delete_description'))
                ->successNotificationTitle(__('tenant.messages.deleted')),
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
                                    ->label(__('tenant.fields.full_name'))
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight('bold')
                                    ->icon('heroicon-m-user')
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('user.email')
                                    ->label(__('tenant.fields.email'))
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->copyMessage(__('tenant.messages.email_copied'))
                                    ->copyMessageDuration(1500),

                                Infolists\Components\TextEntry::make('user.phone')
                                    ->label(__('tenant.fields.phone'))
                                    ->icon('heroicon-m-phone')
                                    ->placeholder(__('—'))
                                    ->copyable(),

                                Infolists\Components\IconEntry::make('status')
                                    ->label(__('tenant.fields.status'))
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
                Infolists\Components\Section::make(__('tenant.sections.overview'))
                    ->schema([
                        Infolists\Components\Split::make([
                            // Card 1: Total Leases
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('leases_count')
                                    ->label(__('tenant.fields.total_leases'))
                                    ->state(fn($record) => $record->leases_count ?? 0)
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('info')
                                    ->icon('heroicon-m-document-text'),
                                
                                Infolists\Components\TextEntry::make('leases_label')
                                    ->label('')
                                    ->state(__('tenant.help.rental_contracts'))
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800']),

                            // Card 2: Active Leases
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('active_leases_count')
                                    ->label(__('tenant.fields.active_leases'))
                                    ->state(fn($record) => $record->active_leases_count ?? 0)
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('success')
                                    ->icon('heroicon-m-home'),
                                
                                Infolists\Components\TextEntry::make('active_label')
                                    ->label('')
                                    ->state(__('tenant.statuses.active'))
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800']),

                            // Card 3: Customer billing is intentionally introduced in Phase 08.
             Infolists\Components\Group::make([
                                 Infolists\Components\TextEntry::make('total_paid')
                                    ->label(__('tenant.fields.customer_billing'))
                                    ->state(__('tenant.help.billing_unavailable'))
                                    ->badge()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('gray')
                                    ->icon('heroicon-m-banknotes'), 

                                Infolists\Components\TextEntry::make('paid_label')
                                    ->label('')
                                    ->state(__('tenant.help.billing_unavailable'))
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800']),

                            // Card 4: Member Since
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label(__('tenant.fields.member_since'))
                                    ->since()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('info')
                                    ->icon('heroicon-m-calendar')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('created_date')
                                    ->label('')
                                    ->state(fn($record) => $record->created_at->format('M d, Y'))
                                    ->color('gray')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),
                            ])->extraAttributes(['class' => 'bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg border border-indigo-100 dark:border-indigo-800']),
                        ])->from('md'),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                // 🔥 SECTION 3: Personal Information
                Infolists\Components\Section::make(__('tenant.sections.personal'))
                    ->schema([
                        Infolists\Components\TextEntry::make('company.name')
                            ->label(__('tenant.fields.company'))
                            ->icon('heroicon-m-building-office')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('move_in_date')
                            ->label(__('tenant.fields.move_in_date'))
                            ->date()
                            ->placeholder(__('—'))
                            ->icon('heroicon-m-calendar-days'),

                        Infolists\Components\TextEntry::make('number_of_occupants')
                            ->label(__('tenant.fields.occupants'))
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-users'),

                        Infolists\Components\IconEntry::make('has_pets')
                            ->label(__('tenant.fields.has_pets'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('warning')
                            ->falseColor('gray'),

                        Infolists\Components\TextEntry::make('pet_details')
                            ->label(__('tenant.fields.pet_details'))
                            ->placeholder(__('—'))
                            ->columnSpanFull()
                            ->visible(fn($record) => $record->has_pets),
                    ])
                    ->columns(4)
                    ->collapsible(),

                // 🔥 SECTION 4: Employment Information
                Infolists\Components\Section::make(__('tenant.sections.employment'))
                    ->schema([
                        Infolists\Components\TextEntry::make('employer_name')
                            ->label(__('tenant.fields.employer_name'))
                            ->icon('heroicon-m-building-office-2')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('job_title')
                            ->label(__('tenant.fields.job_title'))
                            ->icon('heroicon-m-briefcase')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('monthly_income')
                            ->label(__('tenant.fields.monthly_income'))
                            ->money('USD')
                            ->icon('heroicon-m-banknotes')
                            ->badge()
                            ->color('success')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('employment_start_date')
                            ->label(__('tenant.fields.employment_start'))
                            ->date()
                            ->icon('heroicon-m-calendar')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('employer_phone')
                            ->label(__('tenant.fields.employer_phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder(__('—'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('employer_address')
                            ->label(__('tenant.fields.employer_address'))
                            ->placeholder(__('—'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 5: Emergency Contact
                Infolists\Components\Section::make(__('tenant.sections.emergency'))
                    ->schema([
                        Infolists\Components\TextEntry::make('emergency_contact_name')
                            ->label(__('tenant.fields.contact_name'))
                            ->icon('heroicon-m-user')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('emergency_contact_phone')
                            ->label(__('tenant.fields.contact_phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder(__('—'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('emergency_contact_relationship')
                            ->label(__('tenant.fields.relationship'))
                            ->icon('heroicon-m-heart')
                            ->placeholder(__('—')),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 6: Previous Address
                Infolists\Components\Section::make(__('tenant.sections.rental_history'))
                    ->schema([
                        Infolists\Components\TextEntry::make('previous_address')
                            ->label(__('tenant.fields.previous_address'))
                            ->placeholder(__('—'))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('previous_landlord_name')
                            ->label(__('tenant.fields.previous_landlord'))
                            ->icon('heroicon-m-user')
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('previous_landlord_phone')
                            ->label(__('tenant.fields.landlord_phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder(__('—'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('previous_tenancy_start')
                            ->label(__('tenant.fields.tenancy_start'))
                            ->date()
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('previous_tenancy_end')
                            ->label(__('tenant.fields.tenancy_end'))
                            ->date()
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('previous_duration')
                            ->label(__('tenant.fields.duration'))
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
                Infolists\Components\Section::make(__('tenant.sections.identification'))
                    ->schema([
                        Infolists\Components\TextEntry::make('id_type')
                            ->label(__('tenant.fields.id_type'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? __('tenant.id_types.'.$state) : __('—'))
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('id_number')
                            ->label(__('tenant.fields.id_number'))
                            ->placeholder(__('—'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('id_expiry_date')
                            ->label(__('tenant.fields.id_expiry'))
                            ->date()
                            ->placeholder(__('—'))
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
                Infolists\Components\Section::make(__('tenant.sections.references'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('references')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label(__('tenant.fields.name'))
                                    ->icon('heroicon-m-user')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('relationship')
                                    ->label(__('tenant.fields.relationship'))
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('phone')
                                    ->label(__('tenant.fields.phone'))
                                    ->icon('heroicon-m-phone')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('email')
                                    ->label(__('tenant.fields.email'))
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->placeholder(__('—')),
                            ])
                            ->columns(4)
                            ->grid(1)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => !empty($record->references)),

                // 🔥 SECTION 9: Background Check
                Infolists\Components\Section::make(__('tenant.sections.background_check'))
                    ->schema([
                        Infolists\Components\TextEntry::make('background_check_status')
                            ->label(__('tenant.fields.status'))
                            ->badge()
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'approved',
                                'danger' => 'rejected',
                                'gray' => 'not_required',
                            ])
                            ->formatStateUsing(fn ($state) => $state ? __('tenant.statuses.'.$state) : __('—')),

                        Infolists\Components\TextEntry::make('background_check_date')
                            ->label(__('tenant.fields.check_date'))
                            ->date()
                            ->placeholder(__('—')),

                        Infolists\Components\TextEntry::make('background_check_notes')
                            ->label(__('tenant.fields.notes'))
                            ->placeholder(__('—'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                // 🔥 SECTION 10: Internal Notes
                Infolists\Components\Section::make(__('tenant.sections.internal_notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('')
                            ->placeholder(__('tenant.help.no_notes'))
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
