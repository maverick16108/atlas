---
trigger: glob
---

# Business Specifications

## 1. Auction Rules
- **Step**: Minimum increment.
- **Proxy Bidding**: Auto-increment up to `max_bid`.
- **Anti-Sniping**: Extend 5 mins if bid in last 5 mins.

## 2. Post-Auction
- **GPB Logic**:
  - If GPB is flagged participant/observer:
  - 30 min window after auction end.
  - GPB can "Take" the lot at the Winning Price.
  - If GPB takes -> Winner = GPB.
  - If GPB declines/expires -> Winner = Highest Bidder.

## 3. Reporting
## 3. Reporting
- **Protocol**: Protocol of Trading generated immediately after finalization.
- **Analytics**: 
  - Cumulative Year-to-Date (Volume/Value).
  - Activity Monitoring (User participation stats).
- **Scenarios**:
  - Breakdown by **VAT / No VAT**.
  - Breakdown by **Internal Market / Export**.

