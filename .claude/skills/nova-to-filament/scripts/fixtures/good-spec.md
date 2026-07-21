# Spec: Migrate the Admin panel from Nova to Filament v5

**Date**: 2026-07-20
**Nova version**: 4.35.2
**Target Filament version**: ^5.0
**Laravel / PHP**: laravel/framework 11.9; PHP ^8.2
**Audit tooling**: Laravel Boost application-info; composer.lock

## Problem Summary

Plans a one-to-one migration of the Nova admin (3 items audited: 2 direct, 1 rebuild).

## Functional Requirements

### Must Have

- **R1 (resource-User)**: The `app/Nova/User.php:12 (User)` resource MUST be reproduced as a Filament resource preserving its fields and policies.
- **R2 (field-title)**: The `app/Nova/Post.php:20 (Text::make('title'))` field MUST map to `TextInput` preserving its validation.
- **R3 (tool-Revenue)**: The `app/Nova/RevenueTool.php:8 (RevenueTool)` custom Tool MUST be rebuilt as a Filament page.

### Should Have

- Migrate resources in dependency order.

### Could Have

- Consolidate duplicate select options.

### Won't Have (this iteration)

- The legacy impersonation tool.

## Non-Functional Requirements

- **Behavioural parity**: authorization and validation preserved; parity is behavioural, not pixel-level.

## Scope

### In Scope

- R1, R2, R3.

### Out of Scope

- Impersonation.

### Deferred

- Arch tests.

### Migration Sequencing

Topological by BelongsTo edges; standalone resources first: User, then Post.

## Testing Strategy

### Tag Vocabulary

Test approach tags used in this spec:

- `[unit]` — Unit tests targeting individual components in isolation
- `[integration]` — Integration tests exercising boundaries between components
- `[feature]` — Feature/end-to-end tests exercising complete user-facing workflows
- `[manual]` — Manual inspection, observation, or user confirmation
- `[tdd]` — Workflow mode: red-green-refactor loop.
- `[rebuild]` — No direct Filament equivalent; the functionality is rebuilt.

### Acceptance Criteria Coverage

| Requirement | Acceptance Criterion | Test Approach | Disposition |
|---|---|---|---|
| R1 (resource-User) | The Filament User resource exposes the same fields and policy-gated actions as `app/Nova/User.php`. | `[feature]` | direct |
| R2 (field-title) | The `title` field maps to `TextInput` with the same validation rules. | `[integration]` | direct |
| R3 (tool-Revenue) | The `RevenueTool` is rebuilt as a Filament page reproducing its endpoints. | `[feature]` | `[rebuild]` |

### Integration Boundaries

- Filament panel ↔ existing policies.

### Test Infrastructure

None required.

### Unit Testing

Handled at the cpm:do task level.
