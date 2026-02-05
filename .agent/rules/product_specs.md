# Product Specifications & Requirements

## 1. Landing Page (The "Wow" Face)
**Goal**: Attract new clients to register.
**Reference**: Similar vibe to [moneymatika.ru](https://www.moneymatika.ru/) but strictly for Atlas Mining products (Gold/Metals).

### Design Requirements
- **Style**: Ultra-modern, minimalist, premium.
- **Theme**: Dark Mode with Gold Accents (Strict).
- **Key Visual**: Realistic Gold Bar with particle/lighting effects.
- **Animations**: Smooth transitions, hover effects, parallax.
- **Responsiveness**: Perfect on mobile and desktop.
- **Languages**: Multi-language support (RU/EN). **Default: Russian**.

### Functional Elements
- **Hero Section**: Strong value proposition, Gold Bar visual.
- **About / Process**: Simplified explanation of how the auction works.
- **Contact/Registration Form**:
  - Fields: Name, Company, Phone, Email.
  - Action: Submits data to Moderators listing (does NOT auto-create account).
- **Login Button**: Direct access to Client Panel (or Admin).

## 2. Super Administrator Panel
**Goal**: System Management.
### Features
- **Login**: Email/Password.
- **Manage Moderators**:
  - Create/Edit/Block Moderators.
  - Assign permissions.
- **System Settings**: Global config, **Default Theme Settings**.

## 3. Moderator Panel
**Goal**: Operational Management.
### UI Requirements
- **Theme**: Light/Dark Toggle.
- **Dashboard**: Overview of active auctions, pending registrations.

### Features
- **Client Management**:
  - Review registration requests.
  - Create Client Accounts (Organization details, Rep phone number).
  - Grant/Revoke Access (Accreditation).
- **Auction Management**:
  - Create Lots (Metal type, Weight, Purity, Manufacturer).
  - Pricing: Start Price, Step, Reserve Price.
  - Scheduling: Start/End time.
  - Live Monitor: Force pause/cancel.
- **Reports**: View/Export auction results.

## 4. Client Panel
**Goal**: Bidding.
### UI Requirements
- **Theme**: Light/Dark Toggle.
- **Login**: **Phone Number -> SMS Code ONLY**.

### Features
- **Dashboard**:
  - Available Auctions (Upcoming).
  - Active Auctions (Live).
  - My History (Won/Lost).
- **Auction Room (Live)**:
  - Real-time bid updates (WebSockets).
  - **Bidding Interface**:
    - "Bid Now" (Current Price + Step).
    - "Max Bid" (Proxy Bidding setup).
  - **Visual Feedback**:
    - Green background/border: You are winning.
    - Red background/border: You were outbid.
- **Profile**: 
  - Organization info.
  - **Commercial Settings**: Logistics preferences, Basis of delivery.
  - **Limits**: View personal purchasing limits (min volumes, trial lots).

## 4.1 Reporting & notifications
- **Notifications**: Email/SMS/Push on Auction Start, Price Change, Auction End.
- **Scenarios**: Different alerts depending on contract status (e.g. if contract exists with producer).


## 5. Non-Functional Requirements
- **Performance**: Support 50+ concurrent users, <2s latency.
- **Security**: HTTPS, Data Encryption, Secure Session Management.
