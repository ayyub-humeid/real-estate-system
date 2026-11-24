<style>
    /* =====================================================================
       Madar Admin Chrome — Dark-mode overrides
       All rules are scoped to html.dark so they ONLY apply when dark mode
       is active. Light mode uses Filament's own defaults unchanged.
       The login page has its own isolated CSS and is NOT affected here.
       ===================================================================== */

    /* ── Application canvas ── */
    html.dark .fi-panel-admin,
    html.dark .fi-panel-admin .fi-sidebar,
    html.dark .fi-panel-admin .fi-sidebar-header,
    html.dark .fi-panel-admin .fi-topbar,
    html.dark .fi-panel-admin .fi-topbar > nav,
    html.dark .fi-panel-admin .fi-main,
    html.dark .fi-panel-admin .fi-main-ctn {
        background-color: #0D0D0F !important;
    }

    /* ── Tables ── */
    html.dark .fi-ta-ctn {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-ta-record {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-ta-table thead tr {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-ta-table tfoot {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-ta-record:hover {
        background-color: #26262a !important;
    }

    /* ── Sections / Cards ── */
    html.dark .fi-section:not(.fi-aside) {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-section-content-ctn.md\:col-span-2 {
        background-color: #1C1C1F !important;
    }

    /* ── Widgets ── */
    html.dark .fi-wi-stats-overview-stat {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-wi {
        background-color: #1C1C1F !important;
    }
    .fi-wi {
        padding: 15px !important;
    }

    /* ── Dropdowns / Popovers (filter panels, column toggles) ── */
    html.dark .fi-dropdown-panel {
        background-color: #1C1C1F !important;
    }

    /* ── Table "empty state" area ── */
    html.dark .fi-ta-empty-state {
        background-color: #1C1C1F !important;
    }

    /* ── Tabs (infolist / form tabs inside cards) ── */
    html.dark .fi-tabs,
    html.dark .fi-in-tabs,
    html.dark .fi-in-tabs-tab {
        background-color: #1C1C1F !important;
    }

    /* Shield's Roles & Permissions browser uses Form Tabs (.fi-fo-tabs).
       Its canvas must remain distinct from its section cards: #0D0D0F is the
       Madar page canvas, while each permission category stays #1C1C1F. */
    html.dark .fi-fo-tabs,
    html.dark .fi-fo-tabs-tab {
        background-color: #0D0D0F !important;
    }

    /* Infolist tabs power resource overviews and relation details. They use
       different classes from form tabs, so colour both their frame and body. */
    html.dark .fi-in-tabs {
        border: 1px solid rgba(148, 163, 184, .16) !important;
        border-radius: 1rem !important;
        overflow: hidden;
    }
    html.dark .fi-in-tabs-tab {
        min-height: 100%;
    }

    /* Dialogs and slide-overs render outside resource cards. Style them here
       once so every Resource and relation action uses the Madar surface. */
    html.dark .fi-modal-window,
    html.dark .fi-modal-window .fi-section,
    html.dark .fi-modal-window .fi-ta-ctn,
    html.dark .fi-modal-window .fi-tabs,
    html.dark .fi-modal-window .fi-in-tabs,
    html.dark .fi-modal-window .fi-in-tabs-tab {
        background-color: #1C1C1F !important;
        border-color: rgba(148, 163, 184, .16) !important;
    }
    /* The dimmed layer behind a dialog. The panel's slate grey is navy-tinted, which
       showed as blue over the near-black page, so the layer is plain black instead. */
    .fi-modal-close-overlay {
        background-color: rgba(0, 0, 0, .5) !important;
    }
    html.dark .fi-modal-close-overlay {
        background-color: rgba(0, 0, 0, .72) !important;
    }
    html.dark .fi-modal-window {
        border-radius: 1rem !important;
        box-shadow: 0 24px 80px rgba(0, 0, 0, .48) !important;
    }
    html.dark .fi-modal-header,
    html.dark .fi-modal-footer {
        background-color: #1C1C1F !important;
        border-color: rgba(148, 163, 184, .14) !important;
    }
    html.dark .fi-modal-content,
    html.dark .fi-modal-window .fi-in,
    html.dark .fi-modal-window .fi-fo-component-ctn {
        background-color: #1C1C1F !important;
    }
    html.dark .fi-modal-window .fi-in-entry-wrp,
    html.dark .fi-modal-window .fi-fo-repeater-item {
        background: linear-gradient(145deg, #2A2A30, #232328) !important;
        border: 1px solid rgba(148, 163, 184, .22) !important;
        border-radius: .75rem !important;
        padding: .875rem 1rem !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035), 0 4px 14px rgba(0, 0, 0, .14) !important;
    }
    html.dark .fi-modal-window .fi-in-entry-wrp-label {
        color: #a8b4c7 !important;
        font-weight: 700 !important;
    }
    html.dark .fi-modal-window .fi-in-entry-wrp-content,
    html.dark .fi-modal-window .fi-in-text-item {
        color: #f8fafc !important;
    }
    html.dark .fi-modal-window .fi-in-repeatable-item {
        background-color: #202025 !important;
        border: 1px solid rgba(148, 163, 184, .17) !important;
        border-radius: .85rem !important;
        padding: .75rem !important;
    }
    /* Relation actions and resource actions share the same comfortable,
       responsive spacing instead of falling back to Filament's tight modal. */
    @media (min-width: 640px) {
        html.dark .fi-modal-header,
        html.dark .fi-modal-content,
        html.dark .fi-modal-footer {
            padding-inline: 2rem !important;
        }
        html.dark .fi-modal-header {
            padding-block: 1.25rem !important;
        }
    }
    @media (max-width: 639px) {
        html.dark .fi-modal-header,
        html.dark .fi-modal-content,
        html.dark .fi-modal-footer {
            padding-inline: 1.25rem !important;
        }
        html.dark .fi-modal-header {
            padding-block: 1rem !important;
        }
    }
    html.dark .fi-modal-close-btn { color: #cbd5e1 !important; }

    /* ── Select option menus (Choices.js + native fallback) ───────────
       Searchable, multi-select, and regular select fields must share the
       same Madar surface; Choices renders its menu outside the input wrapper. */
    html.dark .fi-fo-select .choices__list--dropdown,
    html.dark .fi-fo-select .choices__list[aria-expanded] {
        background-color: #202024 !important;
        border: 1px solid rgba(148, 163, 184, .20) !important;
        box-shadow: 0 18px 42px rgba(0, 0, 0, .42) !important;
        z-index: 70 !important;
    }
    /* Keep long searchable-option lists inside their own viewport. Without
       this, scrolling a list such as Contract parties can leak into the form
       page and make upward scrolling feel stuck or jumpy. */
    html.dark .fi-fo-select .choices__list--dropdown .choices__list,
    html.dark .fi-fo-select .choices__list[aria-expanded] .choices__list {
        max-height: min(18rem, 45vh) !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        -webkit-overflow-scrolling: touch;
    }
    html.dark .fi-fo-select .choices__list--dropdown .choices__input,
    html.dark .fi-fo-select .choices__list[aria-expanded] .choices__input {
        color: #f8fafc !important;
        background-color: #17171A !important;
        border-radius: .5rem !important;
    }
    html.dark .fi-fo-select .choices__item--choice,
    html.dark .fi-fo-select .choices__item--choice.choices__item--selectable {
        color: #e2e8f0 !important;
        border-radius: .5rem !important;
    }
    html.dark .fi-fo-select .choices__list--dropdown .choices__item--selectable.is-highlighted,
    html.dark .fi-fo-select .choices__list[aria-expanded] .choices__item--selectable.is-highlighted {
        background-color: rgba(255, 255, 255, .08) !important;
        color: #ffffff !important;
    }
    html.dark .fi-fo-select .choices__item--choice[aria-selected='true'] {
        background-color: rgba(255, 255, 255, .13) !important;
        color: #ffffff !important;
    }

    /* Neutral focus state for every input/select (replaces Filament's default
       bright-blue ring and tinted background). */
    html.dark .fi-input-wrp:focus-within,
    html.dark .fi-input-wrp.fi-focused {
        background-color: rgba(255, 255, 255, .05) !important;
        border-color: rgba(148, 163, 184, .55) !important;
        --tw-ring-color: rgba(148, 163, 184, .30) !important;
        box-shadow: 0 0 0 2px rgba(148, 163, 184, .22) !important;
    }
    html:not(.dark) .fi-input-wrp:focus-within {
        border-color: #94a3b8 !important;
        --tw-ring-color: rgba(100, 116, 139, .30) !important;
        box-shadow: 0 0 0 2px rgba(100, 116, 139, .18) !important;
    }
    html.dark .fi-input:-webkit-autofill,
    html.dark .fi-input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 1000px #17171A inset !important;
        -webkit-text-fill-color: #f8fafc !important;
    }
    html.dark .fi-fo-select .choices__group {
        color: #94a3b8 !important;
    }
    html.dark .fi-fo-select .choices__list--multiple .choices__item {
        background-color: rgba(255, 255, 255, .10) !important;
        color: #f1f5f9 !important;
        border-color: rgba(148, 163, 184, .30) !important;
    }
    html.dark .fi-fo-select select,
    html.dark .fi-fo-select select option,
    html.dark .fi-fo-select select optgroup {
        background-color: #202024 !important;
        color: #e2e8f0 !important;
    }

    /* =====================================================================
       Light-mode explicit safeguards
       Ensure form labels, inputs, and all text are readable in light mode.
       ===================================================================== */

    /* Form field labels */
    html:not(.dark) .fi-fo-field-wrp-label,
    html:not(.dark) .fi-fo-field-wrp-label label,
    html:not(.dark) .fi-checkbox-label,
    html:not(.dark) .fi-fo-field-wrp > label {
        color: #111827 !important;
    }

    /* Input wrappers */
    html:not(.dark) .fi-input-wrp {
        background-color: #ffffff !important;
        border-color: #d1d5db !important;
    }

    /* Text inputs & textareas */
    html:not(.dark) .fi-input,
    html:not(.dark) input.fi-input,
    html:not(.dark) textarea.fi-input {
        color: #111827 !important;
        background-color: transparent !important;
    }

    html:not(.dark) .fi-input::placeholder {
        color: #9ca3af !important;
    }

    /* Select inputs */
    html:not(.dark) .fi-fo-select .fi-select-input,
    html:not(.dark) select.fi-select-input {
        color: #111827 !important;
        background-color: #ffffff !important;
    }

    /* Hint / helper text */
    html:not(.dark) .fi-fo-field-wrp-hint,
    html:not(.dark) .fi-fo-field-wrp-help-message {
        color: #6b7280 !important;
    }

    /* Validation error messages */
    html:not(.dark) .fi-fo-field-wrp-error-message {
        color: #dc2626 !important;
    }

    /* Section headings and descriptions */
    html:not(.dark) .fi-section-header-heading {
        color: #111827 !important;
    }
    html:not(.dark) .fi-section-header-description {
        color: #6b7280 !important;
    }

    /* Infolist entries */
    html:not(.dark) .fi-in-entry-wrp-label {
        color: #6b7280 !important;
    }
    html:not(.dark) .fi-in-text-item,
    html:not(.dark) .fi-in-entry-wrp-content {
        color: #111827 !important;
    }

    /* Table cell text */
    html:not(.dark) .fi-ta-cell {
        color: #111827 !important;
    }
    html:not(.dark) .fi-ta-header-cell {
        color: #374151 !important;
    }

    /* Sidebar navigation items */
    html:not(.dark) .fi-sidebar-nav-item-label {
        color: #374151 !important;
    }
    html:not(.dark) .fi-sidebar-group-label {
        color: #6b7280 !important;
    }

    /* Topbar breadcrumbs */
    html:not(.dark) .fi-topbar-breadcrumbs,
    html:not(.dark) .fi-breadcrumbs-item-label {
        color: #374151 !important;
    }

    /* Checkbox / Radio labels */
    html:not(.dark) .fi-checkbox-label-text,
    html:not(.dark) .fi-radio-option-label {
        color: #111827 !important;
    }

    /* Toggle labels */
    html:not(.dark) .fi-toggle-label {
        color: #111827 !important;
    }

    /* Repeater item backgrounds */
    html:not(.dark) .fi-fo-repeater-item {
        background-color: #f9fafb !important;
        border-color: #e5e7eb !important;
    }

    /* Rich-text editor */
    html:not(.dark) .fi-fo-rich-editor .tiptap {
        color: #111827 !important;
        background-color: #ffffff !important;
    }

    /* Stats overview values & labels */
    html:not(.dark) .fi-wi-stats-overview-stat-value {
        color: #111827 !important;
    }
    html:not(.dark) .fi-wi-stats-overview-stat-label {
        color: #6b7280 !important;
    }

    /* Notifications */
    html:not(.dark) .fi-no-notification {
        background-color: #ffffff !important;
        color: #111827 !important;
    }

    /* ── Dark-mode notifications (toasters) ── */
    html.dark .fi-no-notification {
        background-color: #1e1e24 !important;
        border: 1px solid rgba(148, 163, 184, .12) !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .55) !important;
        color: #f1f5f9 !important;
    }
    html.dark .fi-no-notification-title {
        color: #f8fafc !important;
    }
    html.dark .fi-no-notification-body {
        color: #94a3b8 !important;
    }
    /* Status-tinted left border */
    html.dark .fi-no-notification[data-color="success"] { border-left: 3px solid #34d399 !important; }
    html.dark .fi-no-notification[data-color="danger"]  { border-left: 3px solid #f87171 !important; }
    html.dark .fi-no-notification[data-color="warning"] { border-left: 3px solid #fbbf24 !important; }
    html.dark .fi-no-notification[data-color="info"]    { border-left: 3px solid #38bdf8 !important; }

    /* =====================================================================
       Login page — always dark (static design, immune to theme toggle)

       ROOT FIX: Override Filament's gray CSS custom properties at the
       .madar-login container level. Filament labels/text use
       color: rgba(var(--gray-950), ...) — so redefining the variables
       here means every descendant automatically gets dark-theme colors
       without any !important specificity battles.
       ===================================================================== */

    /* Invert all Filament gray tokens inside .madar-login so that
       light-mode Tailwind/Filament utilities render as dark-theme values */
    .madar-login {
        --gray-950: 248, 250, 252;   /* near-white  (was near-black)  */
        --gray-900: 241, 245, 249;
        --gray-800: 226, 232, 240;
        --gray-700: 203, 213, 225;
        --gray-600: 148, 163, 184;
        --gray-500: 100, 116, 139;
        --gray-400:  71,  85, 105;
        --gray-300:  51,  65,  85;
        --gray-200:  30,  41,  59;
        --gray-100:  15,  23,  42;
        --gray-50:    8,  14,  27;
    }

    /* Explicit property overrides as a safety net for anything that
       does not use CSS variables (e.g. hard-coded Tailwind JIT values) */
    html:not(.dark) .madar-login .fi-input-wrp,
    html.dark .madar-login .fi-input-wrp {
        background: rgba(255, 255, 255, 0.045) !important;
        border: 1px solid rgba(148, 163, 184, 0.25) !important;
        border-radius: .85rem !important;
        box-shadow: none !important;
    }
    html:not(.dark) .madar-login .fi-input-wrp:focus-within,
    html.dark .madar-login .fi-input-wrp:focus-within {
        border-color: rgba(148, 163, 184, 0.55) !important;
        background: rgba(255, 255, 255, 0.06) !important;
        box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.14) !important;
    }
    html:not(.dark) .madar-login .fi-input,
    html.dark .madar-login .fi-input,
    html:not(.dark) .madar-login input,
    html.dark .madar-login input,
    html:not(.dark) .madar-login textarea,
    html.dark .madar-login textarea {
        color: #f8fafc !important;
        background-color: transparent !important;
        -webkit-text-fill-color: #f8fafc !important; /* override autofill color */
    }
    html:not(.dark) .madar-login .fi-input::placeholder,
    html.dark .madar-login .fi-input::placeholder,
    html:not(.dark) .madar-login input::placeholder,
    html.dark .madar-login input::placeholder {
        color: #64748b !important;
    }
    /* Labels — target every possible Filament label class */
    html:not(.dark) .madar-login .fi-label,
    html.dark .madar-login .fi-label,
    html:not(.dark) .madar-login .fi-fo-field-wrp-label,
    html.dark .madar-login .fi-fo-field-wrp-label,
    html:not(.dark) .madar-login .fi-fo-field-wrp-label label,
    html.dark .madar-login .fi-fo-field-wrp-label label,
    html:not(.dark) .madar-login .fi-fo-field-wrp-label .fi-label,
    html.dark .madar-login .fi-fo-field-wrp-label .fi-label,
    html:not(.dark) .madar-login .fi-checkbox-label,
    html.dark .madar-login .fi-checkbox-label {
        color: #eef4ff !important;
        font-weight: 700 !important;
    }
    html:not(.dark) .madar-login .fi-input-wrp-icon,
    html.dark .madar-login .fi-input-wrp-icon {
        color: #93a4bb !important;
    }

    /* Browser autofill background override — Chrome injects a solid white/yellow
       background via UA styles that can't be overridden with background-color.
       The standard workaround is an inset box-shadow that covers the autofill tint. */
    .madar-login input:-webkit-autofill,
    .madar-login input:-webkit-autofill:hover,
    .madar-login input:-webkit-autofill:focus,
    .madar-login input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0px 1000px #0d1117 inset !important;
        -webkit-text-fill-color: #f8fafc !important;
        caret-color: #f8fafc;
        transition: background-color 9999s ease-in-out 0s; /* delay autofill takeover */
    }

    /* ── Light-mode: Stats overview cards ── */
    html:not(.dark) .fi-wi-stats-overview-stat {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
    }
    html:not(.dark) .fi-wi-stats-overview-stat-value {
        color: #111827 !important;
    }
    html:not(.dark) .fi-wi-stats-overview-stat-label {
        color: #6b7280 !important;
    }
    html:not(.dark) .fi-wi-stats-overview-stat-description {
        color: #6b7280 !important;
    }
    /* Increase/decrease change badges */
    html:not(.dark) .fi-wi-stats-overview-stat-change {
        color: #374151 !important;
    }

    /* ── Light-mode: Widget wrapper background ── */
    html:not(.dark) .fi-wi {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
    }

    /* ── Light-mode: Dropdown panels ── */
    html:not(.dark) .fi-dropdown-panel {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
    }

    /* ── Light-mode: Section backgrounds (ensure white not gray) ── */
    html:not(.dark) .fi-section:not(.fi-aside) {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
    }

    /* ── Light-mode: Table container ── */
    html:not(.dark) .fi-ta-ctn {
        background-color: #ffffff !important;
    }
    html:not(.dark) .fi-ta-record {
        background-color: #ffffff !important;
    }
    html:not(.dark) .fi-ta-record:hover {
        background-color: #f9fafb !important;
    }
    html:not(.dark) .fi-ta-table thead tr {
        background-color: #f9fafb !important;
    }

    /* =====================================================================
       Dashboard — Enhanced stat cards (AI-aesthetic)
       Uses Filament's per-card --c-* CSS custom properties so every accent
       automatically matches the card's assigned color.
       ===================================================================== */

    /* ── Dark-mode dashboard stat cards ── */
    html.dark .fi-wi-stats-overview-stat {
        background: linear-gradient(145deg, #18181C 0%, #131318 100%) !important;
        border: 1px solid rgba(148, 163, 184, .10) !important;
        border-radius: 1rem !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, .03) inset,
            0 4px 20px rgba(0, 0, 0, .22) !important;
        transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease !important;
        overflow: hidden;
        position: relative;
    }
    html.dark .fi-wi-stats-overview-stat::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: radial-gradient(
            ellipse 120% 80% at 85% 0%,
            rgba(var(--c-400), .08) 0%,
            transparent 65%
        );
        pointer-events: none;
    }
    html.dark .fi-wi-stats-overview-stat::after {
        content: '';
        position: absolute;
        top: 0; left: 10%; right: 10%;
        height: 1px;
        background: linear-gradient(90deg,
            transparent,
            rgba(var(--c-400), .55),
            transparent
        );
    }
    html.dark .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px) !important;
        border-color: rgba(var(--c-400), .22) !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, .04) inset,
            0 8px 32px rgba(0, 0, 0, .32),
            0 0 24px rgba(var(--c-400), .06) !important;
    }
    html.dark .fi-wi-stats-overview-stat-value {
        color: #f1f5f9 !important;
        font-weight: 800 !important;
        letter-spacing: -.03em;
    }
    html.dark .fi-wi-stats-overview-stat-description {
        color: #94a3b8 !important;
        font-size: .78rem !important;
    }
    html.dark .fi-wi-stats-overview-stat-chart {
        opacity: .85;
    }

    /* ── Light-mode dashboard stat cards ── */
    html:not(.dark) .fi-wi-stats-overview-stat {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 1rem !important;
        border-left: 3px solid rgba(var(--c-500), .6) !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04), 0 4px 14px rgba(0, 0, 0, .03) !important;
        transition: transform .22s ease, box-shadow .22s ease !important;
        overflow: hidden;
        position: relative;
    }
    html:not(.dark) .fi-wi-stats-overview-stat::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: radial-gradient(
            ellipse 100% 90% at 90% 0%,
            rgba(var(--c-400), .04) 0%,
            transparent 60%
        );
        pointer-events: none;
    }
    html:not(.dark) .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .07), 0 0 0 1px rgba(var(--c-400), .10) !important;
    }

    /* ── Dark-mode chart widget ── */
    html.dark .fi-wi-chart {
        background: linear-gradient(145deg, #18181C 0%, #131318 100%) !important;
        border: 1px solid rgba(148, 163, 184, .10) !important;
        border-radius: 1rem !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .22) !important;
    }

    /* ── Light-mode chart widget ── */
    html:not(.dark) .fi-wi-chart {
        border-radius: 1rem !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04), 0 4px 14px rgba(0, 0, 0, .03) !important;
    }

    /* ── Widget grid spacing ── */
    .fi-page-dashboard .fi-wi-stats-overview-stats-ctn {
        gap: 1rem !important;
    }

    /* ── Offer page: the heading and next-step sentence get the full width, buttons wrap below ── */
    .page-offer-view .fi-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 1rem !important;
    }
    .page-offer-view .fi-header-heading {
        white-space: nowrap;
    }
    .page-offer-view .fi-header-subheading {
        max-width: 48rem;
    }
    .page-offer-view .fi-header .fi-ac {
        flex-wrap: wrap;
    }
    /* Edit sits on the title row, at its end (left in Arabic, right in English), clear of the workflow buttons. */
    .page-offer-view .fi-header {
        position: relative;
    }
    .page-offer-view .offer-edit-button {
        position: absolute;
        top: 0;
        inset-inline-end: 0;
    }
</style>

