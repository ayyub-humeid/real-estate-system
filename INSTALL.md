# Install the Day 1 Execution Pack

Copy these two items into the **root of your Laravel repository**:

```text
AGENTS.md
real-estate-planning/
```

Target layout:

```text
your-laravel-project/
├── app/
├── database/
├── routes/
├── tests/
├── artisan
├── composer.json
├── AGENTS.md
└── real-estate-planning/
```

If your repository already has `AGENTS.md`, merge the instructions instead of overwriting it.

## Start Phase 01

Open the Laravel repository root as the coding-agent workspace.

Send exactly:

```text
Read real-estate-planning/README.md first.

Then execute:
real-estate-planning/day-1/01-property-acquisition/PROMPT.md
```

When the agent finishes, it must create the Phase 01 `IMPLEMENTATION-REPORT.md`.

Then manually:

```text
1. review changed code
2. test Filament UI
3. test multiple roles/permissions
4. test important notifications
5. inspect important database effects
6. optionally rerun php artisan test
7. commit the stable phase
```

Only then start Phase 02.
