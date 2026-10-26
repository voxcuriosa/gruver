# Curiosa Project Guidelines

## Modularity & File Size Guidelines

- **Maximum File Length**: Keep component, page, and utility files under **300 lines** (max 500 lines for complex views). When a component grows beyond this limit, extract subcomponents (e.g. modals, list cards, filters) into individual files under `src/components/`.
- **Component Separation**: Keep tabs, modals, and complex child layouts in separate subcomponents. Keep JSX clean and view-only.
- **State & Logic Extraction**: Move database operations, state routines, and side-effects into dedicated Custom Hooks under `src/hooks/`. Move pure helper functions to `src/utils/`.

## Karpathy's Core Coding Principles

- **Surgical Changes**: Make diffs as small as the task allows. Do not reformat unrelated code, touch untouched files, or clean up code you were not asked to touch.
- **Read Before You Write**: Read the codebase and existing imports first. Copy existing patterns in the project instead of introducing new conventions or libraries.
- **Dependency Hygiene**: Avoid adding new dependencies unless absolutely necessary. Check if existing libraries or standard APIs can solve the problem first.
- **Verification First**: Base diagnostics strictly on log/error evidence. Fix the underlying root cause rather than papering over symptoms with silent fail-safes.

## Safe Exam Browser (SEB) & Student Privacy

- **Zero Student Tracking**: Follow `.agents/skills/seb-zero-student-tracking/SKILL.md`. Links generated for students (`/seb/start/*`, `/seb/exam/*`, `?seb=1`, `/seb/quit`) must NEVER have any telemetry, analytics, cookies, IP/geo lookup, or visit logging. Visit tracking for `seb` is strictly confined to teacher clicks on the main portal and direct visits to the generator home page (`/seb/`).
