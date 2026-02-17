# Atlas — Инфраструктура

## Общая архитектура

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Docker Network: atlas_net                  │
│                                                                     │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐            │
│  │ atlas_nginx  │   │atlas_backend │   │ atlas_reverb │            │
│  │   (Nginx)    │──▶│  (PHP-FPM)   │   │ (WebSocket)  │            │
│  │  :80 → :80   │   │   :9000      │   │ :8080 → :8080│            │
│  └──────┬───────┘   └──────────────┘   └──────────────┘            │
│         │                                                           │
│         │           ┌──────────────┐   ┌──────────────┐            │
│         │           │atlas_scheduler│  │atlas_frontend│            │
│         │           │  (Transitions)│  │  (Vite Dev)  │            │
│         │           │   daemon      │  │ :5173 → :5174│            │
│         │           └──────────────┘   └──────────────┘            │
│         │                                                           │
│  ┌──────┴───────┐   ┌──────────────┐                               │
│  │  atlas_db    │   │ atlas_redis  │                               │
│  │ (PostgreSQL) │   │   (Redis)    │                               │
│  │ :5432 → :5433│   │ :6379 → :6379│                               │
│  └──────────────┘   └──────────────┘                               │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Контейнеры

### 1. `atlas_nginx` — Веб-сервер

| Параметр | Значение |
|----------|----------|
| **Образ** | `nginx:alpine` |
| **Порт** | `0.0.0.0:80 → 80` |
| **Роль** | Входная точка для всех HTTP-запросов бэкенда |

**Что делает:**
- Принимает все HTTP-запросы к API (`/api/*`)
- Отдаёт статичные файлы из `public/` (включая `/storage/` через symlink)
- Проксирует PHP-запросы на `atlas_backend:9000` через FastCGI
- Проксирует WebSocket-соединения `/app` и `/apps` на `atlas_reverb:8080`

**Конфигурация:** `docker/nginx/conf.d/app.conf`
**Root:** `/var/www/html/public`

---

### 2. `atlas_backend` — PHP-приложение (Laravel)

| Параметр | Значение |
|----------|----------|
| **Образ** | Кастомный (`docker/backend/Dockerfile`) |
| **Порт** | `9000` (внутренний, FastCGI) |
| **Роль** | Основное PHP-приложение, обрабатывает API-запросы |

**Что делает:**
- Запускает PHP-FPM процесс на порту 9000
- Обрабатывает все REST API запросы (`/api/*`)
- Работа с базой данных (PostgreSQL)
- Аутентификация (JWT, OTP)
- Управление аукционами, пользователями, ставками
- Загрузка файлов (аватары и т.д.) в `storage/app/public/`

**Volume:** `./backend:/var/www/html`

> [!IMPORTANT]
> Этот контейнер **не обслуживает HTTP** напрямую. Все запросы приходят через `atlas_nginx`.

---

### 3. `atlas_reverb` — WebSocket сервер

| Параметр | Значение |
|----------|----------|
| **Образ** | Тот же что и backend |
| **Порт** | `0.0.0.0:8080 → 8080` |
| **Роль** | Real-time события через WebSocket (Laravel Reverb) |

**Что делает:**
- Запускает `php artisan reverb:start --host=0.0.0.0 --port=8080`
- Обеспечивает real-time обновления (обратный отсчёт аукциона, новые ставки, уведомления)
- Работает по протоколу Pusher (совместим с Laravel Echo)

**Volume:** `./backend:/var/www/html`

---

### 4. `atlas_scheduler` — Планировщик переходов

| Параметр | Значение |
|----------|----------|
| **Образ** | Тот же что и backend |
| **Порт** | нет (внутренний процесс) |
| **Роль** | Демон автоматических переходов статусов аукционов |

**Что делает:**
- Запускает `php artisan auctions:process-transitions --daemon`
- Автоматически переводит аукционы между статусами по расписанию:
  - `collecting_offers` → `active` (по дате начала)
  - `active` → `gpb_right` (по таймеру торгов)
  - `gpb_right` → `commission` (по таймеру ГПБ)
- Работает непрерывно в фоне

**Volume:** `./backend:/var/www/html`

---

### 5. `atlas_frontend` — Frontend Dev Server

| Параметр | Значение |
|----------|----------|
| **Образ** | `node:20-alpine` |
| **Порт** | `0.0.0.0:5174 → 5173` |
| **Роль** | Vite dev server для Vue.js SPA |

**Что делает:**
- Запускает `npm install && npm run dev -- --host`
- Hot Module Replacement (HMR) для разработки
- Проксирует `/api/*` и `/storage/*` запросы на `atlas_nginx:80`

**Volume:** `./frontend:/app`

> [!NOTE]
> При локальной разработке на хосте (через `npm run dev` на порту 5173) используется `localhost:80` как прокси-цель.
> Docker-версия фронтенда на порту **5174** использует Docker DNS `atlas_nginx:80`.

---

### 6. `atlas_db` — База данных

| Параметр | Значение |
|----------|----------|
| **Образ** | `postgres:15-alpine` |
| **Порт** | `0.0.0.0:5433 → 5432` |
| **Роль** | Основное хранилище данных |

**Что делает:**
- PostgreSQL 15 с базой `atlas`
- Хранит: пользователей, аукционы, ставки, предложения, уведомления, логи активности
- Данные персистятся в Docker volume `pgdata`

**Credentials:**
- Database: `atlas`
- User: `atlas`
- Password: `password`

> [!WARNING]
> Порт проброшен на хост как **5433** (не 5432), чтобы не конфликтовать с локальным PostgreSQL.

---

### 7. `atlas_redis` — Кэш и очереди

| Параметр | Значение |
|----------|----------|
| **Образ** | `redis:alpine` |
| **Порт** | `0.0.0.0:6379 → 6379` |
| **Роль** | Кэширование, очереди, WebSocket backbone |

**Что делает:**
- Бэкенд для Laravel Reverb (pub/sub для WebSocket)
- Кэширование данных
- Очереди задач

---

## Порты на хосте

| Порт | Сервис | Назначение |
|------|--------|------------|
| **80** | Nginx | API бэкенда, статичные файлы |
| **5173** | Node (хост) | Frontend dev server (локальная разработка) |
| **5174** | Node (Docker) | Frontend dev server (Docker-версия) |
| **5433** | PostgreSQL | База данных (смещён с 5432) |
| **6379** | Redis | Кэш и pub/sub |
| **8080** | Reverb | WebSocket сервер |

---

## Два режима запуска фронтенда

### Режим 1: Локально на хосте (рекомендуемый для разработки)
```bash
cd frontend && npm run dev
```
- Доступен на `http://localhost:5173`
- Vite proxy → `http://localhost:80` (nginx)
- Быстрый HMR, нативный file watcher

### Режим 2: Через Docker
- Доступен на `http://localhost:5174`
- Vite proxy → `http://atlas_nginx:80` (Docker DNS)
- Медленнее из-за Docker volume

---

## Частые команды

```bash
# Запуск всех контейнеров
docker compose up -d

# Перезапуск одного контейнера
docker restart atlas_backend

# Логи контейнера
docker logs atlas_backend -f --tail=100

# Выполнить artisan-команду
docker exec atlas_backend php artisan <command>

# Миграции
docker exec atlas_backend php artisan migrate

# Создать storage symlink (обязательно после первого запуска!)
docker exec atlas_backend php artisan storage:link

# Права на storage (если ошибки записи)
docker exec atlas_backend chmod -R 775 /var/www/html/storage
docker exec atlas_backend chown -R www-data:www-data /var/www/html/storage

# Зайти в контейнер
docker exec -it atlas_backend bash

# Подключиться к БД
psql -h localhost -p 5433 -U atlas -d atlas
```

---

## Storage и файлы

```
backend/
├── storage/
│   └── app/
│       └── public/         ← файлы, доступные через web
│           └── avatars/    ← загруженные аватары
├── public/
│   └── storage → ../storage/app/public  ← symlink (php artisan storage:link)
```

> [!CAUTION]
> Symlink `public/storage` создаётся **внутри контейнера** командой `php artisan storage:link`. Без него загруженные файлы (аватары) недоступны через web.

---

## Переменные окружения

Основные переменные задаются в `docker-compose.yml` и `backend/.env`:

| Переменная | Значение | Описание |
|------------|----------|----------|
| `DB_HOST` | `db` | Docker DNS имя PostgreSQL |
| `DB_PORT` | `5432` | Внутренний порт в Docker |
| `DB_DATABASE` | `atlas` | Имя базы |
| `REDIS_HOST` | `redis` | Docker DNS имя Redis |
| `REVERB_HOST` | `0.0.0.0` | Слушать на всех интерфейсах |
| `REVERB_PORT` | `8080` | WebSocket порт |
| `FILESYSTEM_DISK` | `local` | Хранение файлов локально |
