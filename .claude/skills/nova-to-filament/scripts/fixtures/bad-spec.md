# Spec: Broken migration spec (fixture — intentionally invalid)

**Date**: 2026-07-20

## Problem Summary

This fixture is intentionally invalid: it omits the version stamp and has an
empty Disposition cell in the ledger. The structural check MUST reject it.

## Functional Requirements

### Must Have

- **R1 (resource-User)**: reproduce the User resource.

## Testing Strategy

### Tag Vocabulary

- `[feature]` — Feature/end-to-end tests.

### Acceptance Criteria Coverage

| Requirement | Acceptance Criterion | Test Approach | Disposition |
|---|---|---|---|
| R1 (resource-User) | The Filament User resource exists. | `[feature]` | direct |
| R2 (field-title) | The title field maps to TextInput. | `[integration]` |  |
