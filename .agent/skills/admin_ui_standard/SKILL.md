---
name: Admin UI Standard
description: Reference implementation for Admin Panel tables (Table 1) and forms (Form 1) based on the Moderators module.
---

# Admin UI Standard

This skill defines the "Gold Standard" for Admin Panel user interface components, specifically **Table 1** (Data Lists) and **Form 1** (Creation/Editing Modals).

Use these patterns for all future Admin Panel developments to ensure consistency.

## 1. Table 1 (Standard Admin Table)

**Reference Implementation**: `frontend/src/views/admin/Moderators.vue`

### Container Structure
The table should be wrapped in a specific card container with glassmorphism effects.

```html
<div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden shadow-xl h-[calc(100vh-9rem)] flex flex-col">
    <!-- Header / Toolbar (Responsive) -->
    <div class="p-6 border-b border-white/5 flex flex-col md:flex-row gap-4 justify-between items-center bg-dark-900/40">
        <!-- Left Side: Search & Filters -->
        <div class="flex flex-col sm:flex-row gap-4 flex-1 w-full md:w-auto">
            <!-- Search Container -->
            <div class="relative w-full sm:w-auto flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    type="text" 
                    placeholder="Search..." 
                    class="w-full bg-dark-900 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all placeholder-white/20" 
                />
            </div>

            <!-- Optional Filter -->
            <div class="relative w-full sm:w-56 flex-shrink-0">
                 <!-- Select or Filter Component -->
            </div>
        </div>

        <!-- Right Side: Main Action -->
        <button class="flex items-center justify-center gap-2 px-4 py-2 bg-transparent hover:bg-red-500/10 text-red-400 hover:text-red-300 rounded-lg transition-colors font-bold text-sm border border-red-500/50 hover:border-red-400 w-full md:w-auto">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Добавить
        </button>
    </div>

    <!-- Table Container (Scrollable) -->
    <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
        <table class="w-full text-left text-sm text-gray-400 table-fixed">
            <!-- Content -->
        </table>
    </div>
</div>
```

### Table Header (`thead`)
Sticky header with dark background.

```html
<thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
    <tr>
        <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/3">
            <div class="flex items-center gap-2">
                Column Name
                <!-- Sort Icons -->
            </div>
        </th>
        <!-- ... -->
    </tr>
</thead>
```

### Table Body (`tbody`)
Animated rows with hover effects.

```html
<tbody class="divide-y divide-white/5 transition-all duration-300">
    <tr class="hover:bg-white/5 transition-colors group animate-in fade-in duration-300">
        <td class="px-6 py-4">
             <!-- Primary Content -->
             <span class="font-bold text-white text-base">Value</span>
        </td>
        <td class="px-6 py-4 font-mono text-xs">Secondary Value</td>
        <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
            <!-- Action Buttons -->
        </td>
    </tr>
</tbody>
```

### Action Buttons
Standard styling for row actions (Edit/Delete).

```html
<button class="text-gray-500 hover:text-blue-400 transition-colors p-2 rounded-lg hover:bg-blue-500/10" title="Edit">
    <!-- Icon -->
</button>
<button class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-500/10" title="Delete">
    <!-- Icon -->
</button>
```

---

## 2. Form 1 (Standard Admin Modal)

**Reference Implementation**: `frontend/src/components/ui/StandardModal.vue` usage in `Moderators.vue`.

### Modal Configuration
Use `StandardModal` with `theme="red"` for Admin actions.

```vue
<StandardModal 
    :is-open="showModal" 
    title="Title"
    theme="red"
    max-width="max-w-lg"
    @close="closeModal"
>
    <!-- Content -->
</StandardModal>
```

### Input Field Structure
Fields should be wrapped in `space-y-1` blocks within a `space-y-6` form container.

```html
<div class="space-y-1">
     <div class="flex justify-between items-center">
         <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Label</label>
         <span v-if="error" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">Error Message</span>
     </div>
     <input 
        type="text" 
        class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
        :class="error ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
        placeholder="..." 
     />
     <p class="text-[10px] text-gray-500 ml-1">Helper text.</p>
</div>
```

### Modal Actions
Footer buttons layout.

```html
<div class="pt-4 flex gap-3">
    <button @click="close" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
        Отмена
    </button>
    <button @click="save" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
        Сохранить
    </button>
</div>
```

### Focus Management
`StandardModal` automatically handles focus trapping. When the modal is open:
- Focus is trapped within the modal.
- `Tab` cycles through focusable elements.
- `Shift+Tab` cycles backwards.
- Focus does not escape to the browser address bar or sidebar.


## 3. Delete Confirmation Standard

Use specific typography for delete modals.

```html
<div class="text-center pt-2">
    <!-- Title with SAME SIZE/FONT question mark -->
    <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">
        Удалить<span class="text-2xl">?</span>
    </h3>
    <p class="text-white font-bold text-lg mb-2">Item Name</p>
    <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">Warning Text</p>
    <!-- Buttons ... -->
</div>
```

## 4. Confirmation Popup Standard (⚠️ ALWAYS USE INSTEAD OF `confirm()`)

**NEVER use native `confirm()` or `alert()` dialogs.** Always use a `StandardModal` with the following structure for any confirmatory actions (delete, discard changes, send invitations, etc.).

### Structure

```vue
<!-- State -->
const showConfirmPopup = ref(false)

<!-- Trigger: open popup instead of confirm() -->
const onAction = () => {
    showConfirmPopup.value = true
}

const onConfirm = () => {
    showConfirmPopup.value = false
    // perform action
}

<!-- Handle Escape for Stacked Modals -->
<!-- In parent component: -->
<StandardModal 
    :is-open="showMainModal" 
    :close-on-escape="!showConfirmPopup" 
    @close="showMainModal = false"
>
    <!-- ... -->
</StandardModal>

<StandardModal 
    :is-open="showConfirmPopup" 
    @close="showConfirmPopup = false"
>
    <!-- ... -->
</StandardModal>
```

### Template

```html
<StandardModal 
    :is-open="showConfirmPopup" 
    theme="red"
    @close="showConfirmPopup = false"
>
    <div class="text-center pt-2">
        <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">
            Действие<span class="text-2xl">?</span>
        </h3>
        <p class="text-gray-400 text-sm mb-2 font-light">
            Description line
        </p>
        <p class="text-white font-bold text-lg mb-2">
            Entity name or details
        </p>
        <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
            Consequence warning
        </p>
        
        <div class="flex gap-3">
            <button @click="showConfirmPopup = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                Отмена
            </button>
            <button @click="onConfirm" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                Подтвердить
            </button>
        </div>
    </div>
</StandardModal>
```

### Color Variations

| Action Type | Confirm Button Color | Warning Text Color |
|---|---|---|
| Destructive (delete) | `bg-red-600` / `border-red-500/50` | `text-red-400` |
| Discard changes | `bg-red-600` / `border-red-500/50` | `text-red-400` |
| Send / Positive | `bg-emerald-600` / `border-emerald-500/50` | `text-emerald-400` |

### Nested Modals and Backdrop Blur

- **Level 1 Modals** (Main Forms, Standalone Confirmations): Use default backdrop blur (`backdrop-blur="true"`).
- **Level 2 Modals** (Unsaved Changes, Stacked Confirmations): EXPLICITLY disable backdrop blur (`:backdrop-blur="false"`) to maintain context.

### Closing Behavior (Crucial)

1. **Escape Key**: All forms MUST close on Escape.
   - For stacked modals, the parent modal MUST disable `close-on-escape` when a child is open.
   - Example: `:close-on-escape="!showConfirmPopup"` on the parent.
   
2. **Cancel Button**: The "Cancel" button MUST trigger the same "Unsaved Changes" check as closing via X or Escape.
   - **DO NOT** just set `showModal = false`.
   - **DO** call the smart `closeModal()` function.
   
3. **Unsaved Changes Logic**:
   - Store `initialState` when opening/editing.
   - In `closeModal(force = false)`: compare `currentState` vs `initialState`.
   - If different and `!force`: open `showUnsavedModal` and `return`.
   - If same or `force`: close everything.

### Examples from codebase

- **Delete**: `Auctions.vue` — `showConfirmModal` (Level 1, Blur)
- **Unsaved changes**: `Auctions.vue` — `showUnsavedModal` (Level 2, No Blur, `:backdrop-blur="false"`)
- **Send invitation**: `Auctions.vue` — `showInviteModal` (Level 2, No Blur, `:backdrop-blur="false"`)

