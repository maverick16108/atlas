---
name: Perfect Input Field
description: Guidelines and best practices for creating robust, premium input fields with autofill support and validation.
---

# Perfect Input Field

This skill defines the standard for "Perfect" input fields in the application, ensuring a premium user experience, robust autofill handling, and consistent validation states.

## Core Principles

1.  **Visual Feedback**:
    *   **Valid State**: Green border + Green Glow + Green Text (Bold).
    *   **Invalid State**: Red border + Red Glow + Red Text.
    *   **Neutral State**: Slate/Gray styling.
    *   **Pure Colors**: Shadows and glows must match the state color exactly (no gray blur mixing).

2.  **Autofill Robustness**:
    *   **Polling**: Browser autofill can happen silently (without events). Components must verify `document.activeElement` or use a poller (e.g., every 100ms for the first few seconds) to detect value changes that bypass Vue reactivity.
    *   **Forced Sync**: If a mismatch is detected between DOM `.value` and Vue state, force a DOM update (`el.value = state`).
    *   **Styling Override**: Browser default autofill styles (yellow background, black text) must be overridden. Use `:-webkit-autofill` with `-webkit-text-fill-color` matching the validation state.

3.  **UX Details**:
    *   **Focus Management**: Return focus to the input on validation error.
    *   **Masking**: Use eager masking for phone numbers (fill formatting characters as text is entered).
    *   **Prevent Formatting Loss**: Ensure formatted values are preserved even when the browser tries to inject raw data.

## Implementation Pattern (Vue 3)

### Base Component Structure
Create a `BaseInput.vue` that accepts:
- `modelValue`
- `label`
- `error` (string)
- `isValid` (boolean)
- `mask` (optional)

### Autofill Handling Snippet
```typescript
const checkAutofill = () => {
    if (inputRef.value && inputRef.value.value !== props.modelValue) {
        emit('update:modelValue', inputRef.value.value)
    }
}
// Run checkAutofill in an interval on mount
```

### CSS Override Snippet
```css
input:-webkit-autofill,
input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--autofill-color) !important;
    transition: background-color 5000s ease-in-out 0s;
}
```
