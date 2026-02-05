# Technical Architecture Rules

## 1. System Components
- **Backend**: Laravel (Latest Stable).
  - API-first design (`routes/api.php`).
  - Business logic in `app/Services`.
  - Database: PostgreSQL.
  - Queue: Redis.
  - WebSockets: Laravel Reverb or Pusher (Driver: `reverb`).
- **Frontend**: Vue.js 3 (Composition API).
  - Build Tool: Vite.
  - State Management: Pinia.
  - Styling: Tailwind CSS (v3+).

## 2. Infrastructure
- **Docker**: Development environment must use Docker Compose (Nginx, PHP-FPM, Postgres, Redis).
- **Deployment**: Monorepo structure.
  - `/backend`
  - `/frontend`

## 3. Database Guidelines
- Use UUIDs for Primary Keys is optional but recommended for high-sec. (INT is fine for MVP).
- **Transactions**: Critical auction operations (bidding) MUST use DB transactions with appropriate locking (atomic locks in Redis or DB row locking).
