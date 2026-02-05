---
name: Perfect Form Validation
description: Standards for form validation, visual feedback timing, colors, and edit mode behavior.
---

# Perfect Form Validation

This skill defines the standard for implementing "Perfect" form validation in the application, ensuring a premium and non-intrusive user experience.

## Core Philosophy
**"Feedback, not noise."**
-   Never show error (red) or success (green) states before the user has interacted with the field.
-   Forms should open in a "Neutral" state, even if pre-filled (Edit Mode).
-   Validation is reactive, but visual feedback is lazy (waits for `touched`).

## Validation States

There are 3 visual states for any input field:

1.  **Neutral (Default)**
    *   **Condition:** `!touched` (The field has not been blurred or modified yet).
    *   **Appearance:** Gray border (`border-slate-300`), dark text (`text-slate-900`).
    *   **When to use:** On form load, on fresh create, and **crucially** on opening an Edit form.

2.  **Success (Valid)**
    *   **Condition:** `touched && isValid`.
    *   **Appearance:** Green border (`border-green-500`), green text (`text-green-600`), green shadow/glow (`shadow-green-200`).
    *   **When to use:** When the user has finished interacting with a field and it satisfies all requirements.

3.  **Error (Invalid)**
    *   **Condition:** `touched && !isValid`.
    *   **Appearance:** Red border (`border-red-500`), red text (`text-red-600`), red shadow/ring (`shadow-red-200`).
    *   **When to use:** When the user has blurred a field (or attempted submit) and it fails requirements.

## Implementation Guide (Vue 3 + Tailwind)

### 1. State Management
Track the `touched` state for every field independently.

```typescript
const form = ref({
    name: '',
    email: ''
})

const touched = ref({
    name: false,
    email: false
})

const isNameValid = computed(() => form.value.name.length >= 2)
```

### 2. Template Binding ("Lazy Error, Eager Success")

To achieve the "Perfect" behavior:
*   **Green (Success):** Immediate. Lights up as soon as the user types a valid value.
*   **Red (Error):** Lazy. Only appears when the user leaves the field (Blur) or tries to Submit.

This requires tracking **two** states interactions per field:
1.  `dirty`: User has modified the field (`@input`).
2.  `touched`: User has left the field (`@blur`) or attempted submit.

```html
<input
    v-model="form.name"
    @input="dirty.name = true"
    @blur="touched.name = true"
    class="form-input transition-shadow duration-200"
    :class="{
         // NEUTRAL Focus (when typing invalid or clean)
         'focus:ring-indigo-500 focus:border-indigo-500': !formErrors.name && !formSuccess.name,

         // ERROR STATE (Only on Blur/Submit)
         // condition: touched && invalid
         '!border-red-500 !text-red-600 ...': touched.name && !isValid,

         // SUCCESS STATE (Immediate on Input)
         // condition: (dirty || touched) && valid
         // We check 'dirty' so it lights up while typing. We check 'touched' so it stays green if we focus out.
         '!border-green-500 !text-green-600 ...': (dirty.name || touched.name) && isValid
    }"
/>
```

**Implementation Note:**
On **Edit Mode Open**, reset BOTH `dirty` and `touched` to `false`. The form opens Clean.
*   User types valid char → `dirty=true` → **GREEN**.
*   User types invalid char → `dirty=true`, `touched=false` → **NEUTRAL**.
*   User tabs away (invalid) → `touched=true` → **RED**.

> **Note:** For "Required" validation on empty fields, rely on the **Submit** button to trigger the error, rather than `@blur`. This keeps the UI calm (Clean Form) until the user commits to an action or explicitly types invalid data.

### 3. Edit Form Behavior (Critical)
When opening a form to **Edit** existing data:
1.  Pre-fill the `form` model with data.
2.  **RESET** the `touched` object to all `false`.

```typescript
const openEditModal = (item) => {
    // 1. Fill data
    form.value = { ...item }
    
    // 2. Reset visual state (Make it Neutral)
    touched.value = {
        name: false,
        email: false
    }
    
    isModalOpen.value = true
}
```
*Why?* If we don't reset `touched`, a valid pre-filled form will open completely green (Success state), which looks overwhelming and "noisy". We want it to look like a clean form until the user touches a field.

### 4. Submit Behavior
On submit attempt, mark all fields as `touched` to trigger error messages for empty/invalid fields.

```typescript
const submit = () => {
    touched.value = {
        name: true,
        email: true
    }
    
    if (!isFormValid.value) return
    
    // Proceed...
}
```

## CSS Helpers
Ensure `!important` utilities are available or used to override default browser focus styles when in Valid/Invalid states.

```css
/* Example Tailwind Overrides */
.\!border-red-500 { border-color: #ef4444 !important; }
.\!shadow-red-ring { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3) !important; }
```
