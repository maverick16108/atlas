# Design & Style Rules

## 1. Visual Identity
- **Palette**:
  - `Gold`: #FFD700 to #C5A000.
  - `Dark Green`: #0a2f1c.
  - **Status Colors**: #39ff14 (Win), #dc2626 (Lose).
- **Themes**:
  - **Landing**: Dark Mode ONLY.
  - **Client/Mod**: Light/Dark toggle support (`darkMode: 'class'`).

## 2. Frontend Coding
- **Components**: Use `<script setup>`.
- **Naming**: `PascalCase` for components (`AuctionCard.vue`).
- **Tailwind**: Use utility classes. Extract `@apply` only for buttons/inputs used >3 times.

## 3. "The Wow Factor"
- All user-facing pages must have high-quality transitions.
- Use `framer-motion` or `GSAP` equivalents for Vue (`@vueuse/motion`).
