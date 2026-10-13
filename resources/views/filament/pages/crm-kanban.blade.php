<x-filament-panels::page>
    <style>
        .crm-kanban-board .crm-stage {
            background: var(--crm-stage-surface);
            border-color: var(--crm-stage-border);
        }

        .crm-kanban-board {
            display: grid;
            /* Each Stage remains usable at 304px; wider screens automatically
               gain additional columns instead of stretching one large card. */
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr));
            gap: 1.25rem;
        }

        .crm-kanban-board .crm-stage:hover {
            border-color: var(--crm-stage-accent);
            box-shadow: 0 18px 42px -24px var(--crm-stage-accent);
        }

        .crm-kanban-board .crm-stage-accent,
        .crm-kanban-board .crm-stage-dot {
            background-color: var(--crm-stage-accent);
        }

        .crm-kanban-board .crm-stage-card {
            border-inline-start-color: var(--crm-stage-accent) !important;
        }

        .crm-kanban-board .crm-stage-card:hover {
            box-shadow: 0 16px 30px -20px var(--crm-stage-accent);
        }

        .dark .crm-kanban-board .crm-stage {
            background: var(--crm-stage-surface-dark);
            border-color: var(--crm-stage-border-dark);
        }

        .dark .crm-kanban-board .crm-stage:hover {
            border-color: var(--crm-stage-accent);
        }
    </style>

    <div dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="space-y-6" @if ($rtl) style="text-align: right" @endif>
        <x-filament::section icon="heroicon-o-view-columns">
            <x-slot name="heading">{{ __('crm.kanban.title') }}</x-slot>
            <x-slot name="description">{{ __('crm.kanban.drag_hint') }}</x-slot>

            <div class="grid gap-4 {{ auth()->user()->isSuperAdmin() ? 'md:grid-cols-2' : '' }}">
                @if (auth()->user()->isSuperAdmin())
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('crm.fields.company') }}</span>
                        <select wire:model.live="companyId" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                            <option value="">{{ __('crm.fields.company') }}</option>
                            @foreach ($companies as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('crm.kanban.pipeline') }}</span>
                    <select wire:model.live="pipelineId" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" @disabled(! $companyId)>
                        @forelse ($pipelines as $pipeline)
                            <option value="{{ $pipeline->id }}">{{ $pipeline->display_name }}{{ $pipeline->is_default ? ' · ' . __('crm.fields.default') : '' }}</option>
                        @empty
                            <option value="">{{ __('crm.kanban.no_pipeline') }}</option>
                        @endforelse
                    </select>
                </label>
            </div>
        </x-filament::section>

        @if ($stages->isEmpty())
            <x-filament::section>
                <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('crm.kanban.no_pipeline') }}</p>
            </x-filament::section>
        @else
            <div wire:key="crm-board-{{ $pipelineId }}">
                <div class="crm-kanban-board">
                    @foreach ($stages as $stage)
                        @php
                            $openPalettes = [
                                ['accent' => '#0284c7', 'border' => '#bae6fd', 'surface' => '#f0f9ff', 'darkBorder' => '#075985', 'darkSurface' => '#082f49'],
                                ['accent' => '#7c3aed', 'border' => '#ddd6fe', 'surface' => '#f5f3ff', 'darkBorder' => '#5b21b6', 'darkSurface' => '#2e1065'],
                                ['accent' => '#d97706', 'border' => '#fde68a', 'surface' => '#fffbeb', 'darkBorder' => '#92400e', 'darkSurface' => '#451a03'],
                                ['accent' => '#4f46e5', 'border' => '#c7d2fe', 'surface' => '#eef2ff', 'darkBorder' => '#3730a3', 'darkSurface' => '#1e1b4b'],
                                ['accent' => '#0891b2', 'border' => '#a5f3fc', 'surface' => '#ecfeff', 'darkBorder' => '#155e75', 'darkSurface' => '#083344'],
                            ];
                            [$palette, $badgeTone] = match (true) {
                                $stage->stage_type->value === 'won' => [['accent' => '#059669', 'border' => '#a7f3d0', 'surface' => '#ecfdf5', 'darkBorder' => '#047857', 'darkSurface' => '#022c22'], 'fi-color-success'],
                                $stage->stage_type->value === 'lost' => [['accent' => '#e11d48', 'border' => '#fecdd3', 'surface' => '#fff1f2', 'darkBorder' => '#9f1239', 'darkSurface' => '#4c0519'], 'fi-color-danger'],
                                default => match ($stage->code) {
                                'new' => [['accent' => '#0284c7', 'border' => '#bae6fd', 'surface' => '#f0f9ff', 'darkBorder' => '#075985', 'darkSurface' => '#082f49'], 'fi-color-info'],
                                'contacted' => [['accent' => '#0891b2', 'border' => '#a5f3fc', 'surface' => '#ecfeff', 'darkBorder' => '#155e75', 'darkSurface' => '#083344'], 'fi-color-info'],
                                'qualified' => [['accent' => '#7c3aed', 'border' => '#ddd6fe', 'surface' => '#f5f3ff', 'darkBorder' => '#5b21b6', 'darkSurface' => '#2e1065'], 'fi-color-primary'],
                                'viewing' => [['accent' => '#d97706', 'border' => '#fde68a', 'surface' => '#fffbeb', 'darkBorder' => '#92400e', 'darkSurface' => '#451a03'], 'fi-color-warning'],
                                'negotiation' => [['accent' => '#4f46e5', 'border' => '#c7d2fe', 'surface' => '#eef2ff', 'darkBorder' => '#3730a3', 'darkSurface' => '#1e1b4b'], 'fi-color-primary'],
                                default => [$openPalettes[$stage->position % count($openPalettes)], 'fi-color-primary'],
                            },
                            };
                            $stageStyle = "--crm-stage-accent: {$palette['accent']}; --crm-stage-border: {$palette['border']}; --crm-stage-surface: {$palette['surface']}; --crm-stage-border-dark: {$palette['darkBorder']}; --crm-stage-surface-dark: {$palette['darkSurface']};";
                        @endphp
                        <section
                            class="crm-stage group/stage relative min-h-[22rem] overflow-hidden rounded-2xl border p-5 pt-6 shadow-sm transition duration-200 hover:-translate-y-0.5"
                            style="{{ $stageStyle }}"
                            wire:key="stage-{{ $stage->id }}"
                            x-data="{ dragOver: false }"
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="dragOver = false; $wire.moveOpportunity($event.dataTransfer.getData('opportunity'), {{ $stage->id }})"
                            :class="dragOver ? 'scale-[1.01] border-primary-500 bg-primary-50/70 ring-2 ring-primary-500 ring-offset-2 dark:bg-primary-950/30 dark:ring-offset-gray-950' : ''"
                        >
                            <div class="crm-stage-accent absolute inset-x-0 top-0 h-1.5"></div>
                            <header class="mb-5 flex items-center justify-between gap-3 rounded-xl bg-white/60 px-3 py-2.5 shadow-sm dark:bg-black/10 {{ $rtl ? 'flex-row-reverse' : '' }}">
                                <div class="flex min-w-0 items-center gap-2 {{ $rtl ? 'flex-row-reverse' : '' }}">
                                    <span class="crm-stage-dot h-2.5 w-2.5 shrink-0 rounded-full"></span>
                                    <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-gray-950 dark:text-white">{{ $stage->display_label }}</h3>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('crm.stage_types.' . $stage->stage_type->value) }}</p>
                                    </div>
                                </div>
                                <span class="fi-badge {{ $badgeTone }}">{{ $stage->opportunities->count() }}</span>
                            </header>

                            <div class="min-h-52 space-y-4 rounded-xl transition group-hover/stage:bg-white/25 group-hover/stage:p-2 dark:group-hover/stage:bg-black/10">
                                @forelse ($stage->opportunities as $opportunity)
                                    <article
                                        draggable="true"
                                        x-data="{ dragging: false }"
                                        @dragstart="dragging = true; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('opportunity', '{{ $opportunity->id }}')"
                                        @dragend="dragging = false"
                                        :class="dragging ? 'scale-[0.98] cursor-grabbing opacity-50 ring-2 ring-primary-500' : ''"
                                        class="crm-stage-card group cursor-grab rounded-xl border border-s-4 border-gray-200 bg-white p-4 shadow-sm transition duration-150 hover:-translate-y-1 hover:shadow-xl hover:ring-2 hover:ring-primary-200 active:cursor-grabbing dark:border-gray-700 dark:bg-gray-800 dark:hover:ring-primary-900"
                                        wire:key="opportunity-{{ $opportunity->id }}"
                                    >
                                        <div class="flex items-start justify-between gap-2 {{ $rtl ? 'flex-row-reverse' : '' }}">
                                            <div class="min-w-0">
                                                <a href="{{ \App\Filament\Resources\OpportunityResource::getUrl('view', ['record' => $opportunity]) }}" class="block truncate font-semibold text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                                    {{ $opportunity->lead->name }}
                                                </a>
                                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $opportunity->listing?->title ?? __('crm.fields.listing') . ': —' }}
                                                </p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1.5 {{ $rtl ? 'flex-row-reverse' : '' }}">
                                                <span
                                                    title="{{ __('crm.kanban.drag_handle') }}"
                                                    class="inline-flex cursor-grab items-center rounded-md p-1 text-gray-400 opacity-60 transition group-hover:opacity-100 group-hover:text-primary-600 active:cursor-grabbing dark:text-gray-500 dark:group-hover:text-primary-400"
                                                    aria-label="{{ __('crm.kanban.drag_handle') }}"
                                                >
                                                    <x-heroicon-o-bars-3 class="h-4 w-4" />
                                                </span>
                                                <span class="fi-badge fi-color-gray">{{ __('crm.opportunity_types.' . $opportunity->type->value) }}</span>
                                            </div>
                                        </div>

                                        @if ($opportunity->budget_max)
                                            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                {{ number_format((float) $opportunity->budget_max, 0) }} {{ $opportunity->currency }}
                                            </p>
                                        @endif

                                        <div class="mt-3 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400 {{ $rtl ? 'flex-row-reverse' : '' }}">
                                            <span class="truncate">{{ $opportunity->assignedTo?->name ?? __('crm.kanban.unassigned') }}</span>
                                            <span class="fi-badge fi-color-gray">{{ __('crm.sources.' . $opportunity->source->value) }}</span>
                                        </div>

                                        <label class="mt-4 block border-t border-gray-100 pt-3 dark:border-gray-700">
                                            <span class="sr-only">{{ __('crm.kanban.move_to', ['stage' => $stage->label]) }}</span>
                                            <select
                                                class="fi-input block w-full rounded-lg border-gray-200 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900"
                                                wire:change="moveOpportunity({{ $opportunity->id }}, $event.target.value)"
                                            >
                                                <option value="">{{ __('crm.kanban.move_to', ['stage' => '…']) }}</option>
                                                @foreach ($stages as $targetStage)
                                                    @if ($targetStage->id !== $stage->id)
                                                        <option value="{{ $targetStage->id }}">{{ $targetStage->display_label }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </label>
                                    </article>
                                @empty
                                    <p class="rounded-xl border border-dashed border-gray-300 bg-white/50 py-12 text-center text-sm text-gray-500 transition group-hover/stage:border-primary-300 group-hover/stage:bg-white/80 dark:border-gray-700 dark:bg-gray-950/20 dark:text-gray-400">{{ __('crm.kanban.empty') }}</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
