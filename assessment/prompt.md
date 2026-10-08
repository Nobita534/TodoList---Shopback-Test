# Initial AI Prompt — ShopBack Weekly To-Do Planner

You are a senior PHP/Laravel developer assisting me with a small ShopBack take-home coding assessment. Build a working **Weekly To-Do Planner** that I can run locally, test, explain, and submit.

## 0. Mandatory instructions — read first

1. **Before planning, generating, or modifying any code, open and read `assessment/rules.md` in the project root in full.** This prompt and `assessment/rules.md` must be used together. Do not start implementation if `assessment/rules.md` is missing or unreadable; instead, tell me what is missing.
2. Treat `assessment/rules.md` as the source of truth for UI conventions, database constraints, business rules, testing requirements, and in-scope/out-of-scope boundaries. If this prompt and `assessment/rules.md` conflict or leave an important decision ambiguous, point it out and ask rather than silently inventing behavior.
3. This is a **coding assessment**, not a production SaaS application. Implement the simplest maintainable solution meeting the acceptance criteria; avoid speculative features, unnecessary abstractions, and extra dependencies.
4. **Do not edit, overwrite, reformat, or regenerate `prompt.md`.** This file must remain an exact record of my initial AI prompt for the assessment.
5. Work in clear phases. First inspect the repository and present a concise implementation plan and intended files; then implement backend, frontend, and tests. State assumptions and explain any material corrections. Do not claim success without actually running the relevant checks.

## 1. Product overview

Create a single-page **Weekly To-Do Planner** that displays one week at a time as **seven columns, Monday through Sunday**. Users organize tasks by the date on which they plan to work and by **estimated duration in hours**. Duration is *not* a time slot: there are **no start-time or end-time fields**.

The application must support:
- Add, edit, and delete tasks.
- Mark tasks complete and incomplete.
- Persist tasks in SQLite so they survive page reloads, app restarts, and week navigation.
- See the planned number of hours for each day.
- Navigate backward and forward between permitted weeks within a one-calendar-month planning window.

## 2. Fixed technology stack

- Backend: PHP with Laravel, following standard Laravel MVC conventions.
- Frontend: Blade templates + Bootstrap 5, with minimal JavaScript only where necessary for modal interactions or usability.
- Database: SQLite with Laravel migrations and Eloquent ORM.
- Testing: **PHPUnit**, especially Laravel HTTP Feature Tests (do not switch to Pest).
- Architecture: server-rendered monolith. Do not create a separate REST API or SPA.

Use the Laravel version compatible with the available local PHP/Composer environment. Reuse an existing project if one is present. Keep setup reproducible and document any commands or prerequisites in `README.md`.

## 3. Task data and validation

Create a `tasks` table with these fields:

| Field | Rule |
| --- | --- |
| `id` | Primary key |
| `title` | Required, trimmed, non-blank, max 255 characters |
| `description` | Optional text |
| `estimated_hours` | Required decimal number from 0.5 to 24, in 0.5-hour increments |
| `priority` | `low`, `medium`, or `high`; default `medium` |
| `scheduled_date` | Required date within the currently allowed planning window when scheduling a task |
| `due_date` | Required date, on or after `scheduled_date`; may fall outside the planning window |
| `notes` | Optional text |
| `is_completed` | Boolean, default `false` |
| `created_at`, `updated_at` | Standard timestamps |

Validate on the **server**; client-side constraints are only additional guidance. Do not allow invalid durations, blank titles, invalid priorities, or an earlier deadline. Avoid redundant database columns for calculated UI states such as overdue.

## 4. Weekly navigation and one-month planning window

These are core business rules; implement and test them carefully:

1. Use the application's current date in timezone **`Asia/Ho_Chi_Minh`**. Do **not** hardcode October 2026 or any specific week into business logic.
2. The allowed scheduling dates run **inclusively from today through the same calendar date one month later**, using safe calendar-month arithmetic (no month-end overflow). For example, if today is 2026-10-08, the window is 2026-10-08 through 2026-11-08, inclusive.
3. On first load, show the week **containing today**, Monday through Sunday.
4. Always render **all seven day columns**, including the partial first and last weeks. Dates outside the allowed scheduling window remain visible but must be disabled in the Add Task date selector and rejected by server-side validation when creating or rescheduling tasks.
5. **Sunday remains a normal task day** with task cards and CRUD actions. Place a **Next Week** control at the bottom of the Sunday column. Enable it only when the next week intersects the allowed scheduling window; otherwise show it disabled.
6. Provide a **Previous Week** control, preferably near the week heading, to revisit earlier displayed weeks. Disable it on the first permitted week. Neither control may navigate outside the weeks intersecting the planning window.
7. Navigating between weeks must **never delete, reset, or overwrite** tasks. Filter the visible tasks by their `scheduled_date` for the selected week, while retaining all records in SQLite.
8. Calculate and validate the current visible week on the server. Do not trust an arbitrary week/date value from the URL. Preserve the selected valid week after task operations when practical.
9. The planning window is a rolling demo window derived from the application's **current date** at request time, not a hardcoded October–November range. Do not introduce month filters, a calendar picker, or a historical dashboard.

## 5. Backend implementation

Build only what the application needs:

- Migration for `tasks` and one Eloquent `Task` model.
- Web routes and controller actions for the weekly board and task **create, update, delete, and toggle completion** operations.
- Laravel request validation and CSRF protection; conventional HTTP methods, redirects, and flash feedback.
- Correct task grouping by scheduled day; sorting in each day: **incomplete tasks first**, then priority **high → medium → low**, then oldest creation first; completed tasks last.
- Daily `planned_hours` calculated as the sum of the estimated durations of **all** tasks scheduled that day, **including completed tasks**. Do not mislabel this number as remaining time or actual time spent.
- A task is **overdue** when its deadline is before today and it is incomplete; **due today** when deadline equals today and it is incomplete. Completion overrides overdue styling. Compute these display states without storing them as additional database fields.

Keep controllers and views readable. A small helper/service for week-date calculations is acceptable if it demonstrably improves clarity; do not introduce a repository layer or extensive design patterns merely for appearance.

## 6. Frontend/UI implementation

Build a clean, light/white, responsive **one-page board**:

- Header with title **Weekly To-Do Planner** and **+ Add Task** button at the far right.
- Visible week label/date range and a Previous Week control.
- Seven columns labeled **Monday through Sunday**, each with the corresponding day/month and a **planned hours** total.
- Task cards constrained visually to their scheduled-day columns. Each card shows at minimum: **title, estimated hours, priority, deadline, completion checkbox, Edit button, and Delete button**. Show description/notes where helpful without making cards excessively tall.
- Put Edit and Delete controls on the **right-hand side of each task card**.
- Add and Edit actions use Bootstrap modal forms with all seven user-facing task fields. Show helpful field-level errors and preserve submitted input if validation fails.
- Delete asks for confirmation before removing the record.
- Mark Complete is reversible and visibly distinguishes completed tasks.
- Sunday contains normal task cards plus its Next Week button at the bottom; the button is disabled on the last permitted week.
- Dates outside the planning window are visibly disabled for new scheduling, while their day columns remain visible.
- Keep seven columns on desktop; use a simple horizontal-scroll layout on narrow screens rather than squeezing cards to unreadable widths.
- Show useful empty states and clear success/error feedback. Use restrained visual styling and accessible labels.

Do not add login, accounts, user profiles, dark mode, charts, drag-and-drop, notifications, recurring tasks, start/end time slots, search/filter pages, or a separate dashboard.

## 7. PHPUnit testing requirements

Write meaningful Laravel **PHPUnit Feature Tests** with an isolated SQLite testing database (preferably in-memory). Freeze/mock the current date where appropriate so date-boundary tests are deterministic.

Cover at least:

1. Weekly board opens on the week containing today and renders seven days.
2. Valid task creation persists all expected fields.
3. Invalid task creation is rejected: blank title, invalid duration/increment, invalid priority, scheduled date outside the allowed window, or due date before scheduled date.
4. Updating a task persists changes; moving its scheduled date changes which day/week displays it.
5. Deleting a task removes it from SQLite.
6. Completing and reopening a task both work.
7. Daily planned hours correctly include completed tasks.
8. Week navigation preserves data and blocks navigation outside the permitted weeks.
9. Sunday supports tasks and the Next Week control; Next Week is disabled on the final permitted week.
10. A task remains persisted after page reload/subsequent requests.

Run the tests and report the actual pass/fail result. Fix failures before considering the task complete. Also perform manual checks for modal behavior, layout, and task-card controls; automated backend tests alone cannot prove visual correctness.

## 8. Deliverables and assessment integrity

The project must be easy to run and understand. Provide:

- Complete working Laravel source, migrations, views, and tests.
- `README.md` with prerequisites, installation, SQLite setup/migrations, launch instructions, and test command.
- A `screenshots/` directory for **real screenshots of the working UI/features**; do not fabricate screenshots or claim screenshots were captured if they were not.
- Keep the original `prompt.md` unchanged and follow `assessment/rules.md` throughout implementation.

The assessment also requires `reflection.md` (maximum **500 words**) about my problem breakdown, AI mistakes and corrections, decisions I did not delegate, and what I would improve with more time. **Do not invent personal reflections or fabricate AI mistakes on my behalf.** I will write/review that document based on actual development work.

## 9. Acceptance criteria and workflow

The implementation is complete only if:

- Task CRUD, reversible completion, SQLite persistence, and all seven task attributes work.
- Week navigation and its first/last-week boundaries match the business rules.
- Sunday supports both tasks and Next Week.
- The one-month scheduling restriction is enforced on both frontend and backend.
- Daily planned hours and task statuses are correct.
- PHPUnit tests pass, and the setup instructions reproduce a working app.
- No features outside the defined ShopBack test scope have been added without my approval.

**Start now by reading `assessment/rules.md`, inspecting the existing project, and showing your concise implementation plan. Then carry out the implementation in backend → frontend → testing order, giving me a factual summary of files changed, tests run, unresolved issues, and any choices needing my review.**
