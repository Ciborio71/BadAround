# NF-UX-04 — First-choice collapse fix and automatic next-question focus

The owner reproduced uncollapsed alternatives after choosing a new answer. A real Chromium pointer click from an empty form reproduced the bug: selecting vehicle left all six categories visible. Previous browser checks clicked answers that had already been prefilled, missing the browser default-action ordering.

## Fix

- Commit a radio choice using a cancellable event-loop task after the browser dispatches its native input/change events, rather than a microtask that could run before the canonical select was updated. Click and change-only assistive input share the same commit path.
- Compact only the committed answer, retaining the selected card and Modifica. Reopening cancels pending commit work.
- Remove blur-only compaction, which could move an edit button between pointer-down and click. Arrow exploration stays open; Space/Enter or pointer selection confirms.
- After a valid committed choice, focus the next visible unanswered/unconfirmed field within the current step. Scroll only when necessary to expose the next question/control below the sticky header; honor reduced motion.
- At the end of a step, focus Continue without clicking it. Never advance the wizard page, activate Google automatically, or submit.
- Respect canonical media guards, disabled/locked states, disposed instances and invalidated dependent selections. No new canonical data, validators, backend, media enablement or Smart Address changes.
- Step 1 explains the automatic same-step progression and Modifica behavior.

## Verification

- Actual browser regression starts with an empty form and clicks vehicle → stolen vehicle → relationship, checking that each completed group shows one answer and that focus moves to the next question, then Continue. Model prefilling is used only afterwards for independent smoke checks.
- Browser suite covers 320/390/768/1440px, compact/edit interactions, keyboard arrows, paired fields and all seven steps.
- Existing compact DOM expectations updated for the explicitly requested next-question focus; original payload/lock/parent-invalidation and full Native/Smart Address/media regression gate retained (139 tests).

Deployment scope: wizard.js and template-parts/native-report/step.php only. Staging-only deployment with rollback to b59ba8c. No real submission or database mutation.
