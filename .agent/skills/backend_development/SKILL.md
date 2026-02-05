---
name: Backend Development
description: Guidelines for Laravel 11 backend development.
---

# Backend Guidelines (Laravel 11 + PostgreSQL)

## Общие принципы
- API-only
- PostgreSQL
- backend — доверенная сторона

## Структура
- Controllers
- Requests / DTO
- Services
- Policies
- Jobs (SMS, 1С)

## Авторизация
- телефон + OTP
- без паролей
- rate-limit, TTL

## Заявки
- сервисный слой
- статусы enum
- изменения только из 1С

## Валидация
- обязательна
- арматура только из спецификаций

## Интеграция
- отдельный слой
- очереди
- логирование

## Запрещено
- редактировать статусы
- менять статус без 1С
