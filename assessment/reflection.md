# AI Usage Reflection — ShopBack Weekly To-Do Planner

## 1. How did you break down the problem before prompting?

Before using Codex, I identified ShopBack's minimum requirements: task CRUD, completion status, and data persistence. I then defined a small, concrete product: a weekly planner with seven columns (Monday–Sunday), estimated hours instead of time slots, task priorities, scheduled dates, and deadlines. I specified a one-month scheduling window, week navigation, and the behavior of the Sunday column. I chose Laravel, Blade, Bootstrap, SQLite, and PHPUnit, then separated the implementation request (`prompt.md`) from the business and technical constraints (`rules.md`). This helped me communicate both what to build and what to avoid.

## 2. What did the AI get wrong, and how did you fix it?

The first implementation did not consistently display success or failure feedback after user actions. Its initial deletion confirmation also did not match the popup interaction I wanted. I reviewed the UI, identified these mismatches, and sent follow-up prompts explicitly requesting visible feedback and a proper confirmation modal. In those prompts, I reminded Codex to follow `rules.md` rather than inventing its own UI behavior. I then checked the updated screens and interactions again.

## 3. What did you deliberately not delegate to AI, and why?

I kept responsibility for defining the product scope, task fields, scheduling rules, and acceptance criteria. I also reviewed the generated interface and manually exercised key workflows instead of treating the AI's implementation as automatically correct. These decisions required understanding the user's experience and the assessment requirements; delegating them entirely would have made it harder to detect unwanted behavior or unnecessary features.

## 4. What would you do differently with more time?

I would perform more systematic end-to-end testing across browsers and screen sizes, including keyboard-only navigation and accessibility checks. I would also improve the clarity of validation messages and document manual test scenarios alongside the screenshots. I would prioritize reliability and usability rather than adding features outside the assessment scope.
