# RULES — SHOPBACK WEEKLY TO-DO PLANNER (ENGLISH)

> Constraints for Codex while building this application. **Read this file in full together with `prompt.md` before writing or modifying code.** This is a small take-home coding assessment, not a production SaaS product.

## 1. Precedence and workflow

- `prompt.md` specifies the objective and implementation request; `rules.md` defines boundaries, concrete behavior, and verification criteria.
- If the files conflict, a requirement is ambiguous, or a requirement is infeasible, **pause that part and ask the user**. Never invent business behavior silently.
- Before coding, inspect the existing repository; provide a concise plan, list of intended file changes, and any assumptions requiring confirmation.
- Work in this order: **Backend → Frontend → PHPUnit tests → manual checks → documentation**.
- Prefer the simplest maintainable implementation. Do not add layers, packages, patterns, or features merely to appear sophisticated.
- **Never modify, reformat, overwrite, or regenerate `prompt.md`.** It is the exact record of the initial AI prompt for ShopBack submission.
- Do not claim that a feature or test works unless it has actually been verified.

## 2. Mandatory stack and architecture

| Area | Technology / rule |
| --- | --- |
| Backend | PHP + Laravel, following standard MVC conventions |
| Frontend | Blade + Bootstrap 5; minimal JavaScript |
| Database | SQLite + Laravel migrations + Eloquent ORM |
| Testing | PHPUnit (Laravel HTTP Feature Tests), **not Pest** |
| Architecture | Server-rendered monolith; no SPA or separate REST API |

- Use a Laravel version compatible with the available PHP/Composer environment; reuse an existing project where possible.
- One `tasks` table and the necessary model, routes, and controllers are sufficient. A small week-calculation helper/service is allowed if it improves clarity; a Repository Pattern is not required.
- Use web routes, CSRF protection, appropriate HTTP methods, server-side validation, redirects, and feedback messages.
- Render user text with Blade's default escaping; do not render untrusted user content as raw HTML.

## 3. Database schema and validation

Use **one `tasks` table only**. Do not add `users`, `categories`, or `task_logs` tables.

| Column | Rule |
| --- | --- |
| `id` | Primary key |
| `title` | Required; trim whitespace; non-blank; max 255 characters |
| `description` | Optional TEXT |
| `estimated_hours` | Required decimal, **0.5 through 24** in **0.5-hour increments**; no start/end time |
| `priority` | Only `low`, `medium`, `high`; default `medium` |
| `scheduled_date` | Required DATE; a newly scheduled date must be inside the permitted window |
| `due_date` | Required DATE, **>= `scheduled_date`**; may be outside the one-month scheduling window |
| `notes` | Optional TEXT |
| `is_completed` | BOOLEAN, default `false` |
| `created_at`, `updated_at` | Standard Laravel timestamps |

- **Server-side validation is mandatory**; HTML `required`/`min`/`max`/`step` constraints merely improve usability.
- Reject a whitespace-only title, durations that are zero/negative/>24/not multiples of 0.5, invalid priorities, and deadlines before scheduled dates.
- When **creating a task** or **changing its `scheduled_date`**, the new date must be within the allowed window at request time.
- When **editing an existing task without changing `scheduled_date`**, allow the original date to remain even if it has passed. Users must be able to edit title/notes/priority or mark a task complete without forcibly rescheduling it. Reject changes to a date outside the permitted window.
- Do not store calculated fields such as `overdue`, `due_today`, `remaining_hours`, or `planned_hours`.

## 4. Date, week, and navigation rules

1. Use the application's current date in the **`Asia/Ho_Chi_Minh`** timezone. Never hardcode October 8, 2026 or a fixed number of weeks.
2. The **rolling planning window** is **today through the same calendar date one month later, inclusive**. Use calendar-month addition with **no month-end overflow** (e.g., Carbon `addMonthNoOverflow()`), not a fixed 30-day interval.
3. On first load, display the week **containing today**, always **Monday through Sunday**, even if today is Thursday or Sunday.
4. Always render **all seven columns**. Dates before today in the first week and beyond the end boundary in the last week stay visible but **cannot receive newly created or rescheduled tasks**.
5. **Sunday is a normal task day**: it supports task creation, cards, Edit/Delete/Complete. Place the **Next Week** button at the **bottom of the Sunday column**.
6. Place **Previous Week** near the week heading. Allow navigation only among weeks that intersect the permitted scheduling window. Disable Previous on the first week and Next on the last week.
7. Users may navigate at any time; they **do not have to wait until Sunday**.
8. Validate the displayed week parameter on the server (e.g., a Monday date representing the requested week). Do not blindly trust arbitrary URL date values.
9. Week navigation only changes which tasks are shown by `scheduled_date`; it **never deletes, resets, or mutates SQLite records**. Preserve the selected week after task operations where practical.
10. Recalculate the window from the **current date on each request**; it does not remain anchored to the app's first launch. Old tasks remain stored in SQLite even if no longer reachable using the limited week navigation.

## 5. Task business rules

- **Add:** `+ Add Task` opens a Bootstrap modal with all seven user-facing fields: Title, Description, Duration, Priority, Scheduled Date, Deadline, Notes. On success the task appears in its scheduled day.
- **Edit:** pre-filled modal; edits to valid fields are allowed. A valid date change moves the card to its new day/week. Do not implicitly change completion status.
- **Delete:** confirm before deleting; remove the correct SQLite record.
- **Complete:** card checkbox toggles `is_completed` `false ↔ true`; the action must be reversible.
- **Sorting within each day:** incomplete tasks first; among incomplete tasks sort `high → medium → low`; within equal priorities show older `created_at` first; completed tasks last (with stable ordering, e.g., by creation time).
- **Daily planned hours:** sum `estimated_hours` for **all** tasks on that day, **including completed tasks**. Never label this as actual time spent or remaining hours.
- **Overdue:** deadline before today and incomplete. **Due Today:** deadline equals today and incomplete. **Completed:** takes precedence over overdue styling.
- No time slots, start/end times, timer, or automatic hour-by-hour placement.

## 6. UI/UX rules

- One **weekly board** screen, **light/white and minimal**, with readable text; no secondary analytics dashboard.
- Header: **Weekly To-Do Planner** on the left and **+ Add Task** at the far right.
- Near the header: the visible week's date range and Previous Week.
- **Seven Monday–Sunday columns:** each contains day name, day/month, daily planned hours, and only that day's task cards.
- Each card displays at least **Title, Duration, Priority, Deadline, Complete checkbox, Edit, Delete**; Edit/Delete controls are on the **right side of the card**. Keep descriptions/notes compact or in the modal.
- Use **Bootstrap modals** for Add/Edit, show field-level validation errors, and preserve submitted input after a validation failure.
- Confirm deletion; display clear success/error feedback and an empty state for an empty day.
- Completed cards are visually distinct. Priority and deadline states should be recognizable without excessive decoration.
- Out-of-range dates remain visible but clearly unavailable for scheduling. The server must also reject out-of-range dates.
- Keep seven columns on desktop; allow **horizontal scrolling** on small screens instead of squeezing cards into unreadable widths. Use accessible labels and usable controls.
- No extra animations, charts, widgets, or CSS libraries without a concrete need.

## 7. PHPUnit testing rules

- Use **PHPUnit only**, preferably **Laravel Feature Tests**, with an isolated SQLite test database (ideally `:memory:`) and `RefreshDatabase` to protect actual data.
- Freeze/mock time for date-dependent tests so results are deterministic, especially near month ends.
- Minimum coverage:
  1. The board opens on the week containing today and renders Monday–Sunday (all seven columns).
  2. Valid task creation persists every required value in SQLite.
  3. Reject blank/whitespace-only title, duration outside bounds or off 0.5 increments, invalid priority, scheduled date outside the permitted window, and deadline before scheduled date.
  4. Edit persists changes; changing the date moves the task to another day/week; editing other fields on an older scheduled task does not force rescheduling.
  5. Delete removes the correct task.
  6. Complete and Uncomplete both work.
  7. Planned hours include completed tasks; sorting follows the rules.
  8. Previous/Next cannot cross the boundaries; navigating does not lose tasks.
  9. Sunday supports tasks and Next Week; Next is disabled on the final week.
  10. Data remains after refresh/subsequent requests; month-addition edge cases do not overflow.
- Actually run tests and report **the command, pass/fail counts, and remaining failures**; never report PASS without executing tests.
- Manually check modal actions, displayed validation, cards, delete confirmation, checkboxes, horizontal scrolling, and week navigation. PHPUnit alone cannot prove UI correctness.

## 8. Explicitly out of scope

**Do not build any of the following without explicit user approval:**

- Authentication, authorization, login, user management, multi-user roles.
- Separate REST API, SPA, React, Vue, microservices.
- Drag-and-drop, manual drag sorting, calendar picker/month-week filters, search, analytics dashboards, charts.
- Recurring tasks, notifications, email, external calendar integrations, AI suggestions, actual time tracking.
- Start Time/End Time, time-slot scheduling, multi-device sync.
- Repository Pattern, CQRS, event bus, background queues, unnecessary packages.
- Required demo seed data, production deployment, or CI/CD beyond this coding assessment.

## 9. Deliverables and definition of done

- Runnable Laravel source with migrations, Blade views, PHPUnit tests, and SQLite persistence.
- `README.md`: prerequisites, dependency installation, SQLite configuration, migrations, app startup, PHPUnit commands.
- `screenshots/`: **authentic screenshots** of the functioning UI and key features. Never fabricate them or claim they were captured if they were not.
- Preserve the exact initial `prompt.md`; use `rules.md` to track constraints and decisions.
- `reflection.md`: maximum **500 words**. The user must write/verify the account of actual problem breakdown, AI mistakes and corrections, decisions intentionally not delegated, and future improvements. **Never invent AI mistakes or the user's experience.**
- Completion requires working CRUD, reversible completion, validation, persistence, seven-day navigation, one-month bounds, planned hours, and passing PHPUnit tests.
- End each Codex session with a factual report of **files changed, commands/tests run, actual results, unresolved issues, and decisions requiring review**.
