# Security Limits

## 1. Authentication
- **Client Login**: STRICTLY Phone + SMS Code. No Password login for Clients.
- **Admin Login**: Email/Password + 2FA (optional).

## 2. Aucton Integrity
- **Rate Limiting**: `60 requests/min` for standard API, `1 req/sec` for Bidding endpoints per user.
- **Validation**:
  - Always validate `bid_amount > current_price + step`.
  - Check `user->is_accredited`.
  - Check `auction->status === ACTIVE`.

## 3. Data Protection
- Phone numbers must be stored in standard E.164 format.
- Logs must not contain SMS codes.
