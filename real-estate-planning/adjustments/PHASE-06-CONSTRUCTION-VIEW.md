# Phase 06 — Construction view: stages are the milestones

_Status: approved by the developer on 2026-11-22. UI only: no schema change, `ConstructionService` untouched._

## Decision

There is no separate milestone table. A **work package is the milestone** (foundation, structure,
finishing): it already has dates, a status and a completion flow. The task level stays as optional
detail one tab away.

## What changed

- Project → Construction tab: a short list with one **Open** button (was a ~20-action menu where
  almost every action made the user pick a package or task from a dropdown).
- New construction page: overall progress, a next-step sentence, lifecycle actions (start,
  complete, edit, cancel) and four tabs: **Stages** (main view), Tasks, Issues, Delays.
- Stages tab: one row per work package with responsible party, planned end, progress, status and a
  **schedule** badge (on track / needs attention / overdue) computed in one place
  (`ConstructionHealth`), plus open-issue and delay counts.
- Every action opens on its own row, so nothing asks for the stage or task again.
- Failures show a persistent notification (`RunsWorkflowAction`).

## Action audit

| Record | View | Create | Edit | Delete | Workflow |
|---|---|---|---|---|---|
| Construction | page | Project tab | page | Project tab (empty planned only) | start, complete, cancel |
| Stage | Stages tab | Stages tab | row | row (planned only) | start, complete, cancel |
| Task | Tasks tab | stage row | row | row (planned only) | start, progress, correct progress, inspection, complete, cancel |
| Issue | Issues tab | stage row | — | — | start, resolve, close |
| Delay | Delays tab | stage row | — | — | — |

Intentionally excluded (history rules): editing or deleting an issue, a delay, a progress update or
an inspection. Progress changes only through "Correct progress", which keeps the original.
