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

    /* ── Dropdowns / Popovers (filter panels, column toggles) ── */
    html.dark .fi-dropdown-panel {
        background-color: #1C1C1F !important;
    }

    /* ── Table "empty state" area ── */
    html.dark .fi-ta-empty-state {
        background-color: #1C1C1F !important;
    }

    /* ── Tabs (infolist / form tabs inside cards) ── */
    html.dark .fi-tabs {
        background-color: #1C1C1F !important;
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
        border-color: rgba(96, 165, 250, 0.85) !important;
        background: rgba(30, 58, 138, 0.16) !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14) !important;
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
</style>

