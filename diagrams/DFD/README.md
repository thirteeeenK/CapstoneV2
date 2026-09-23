# SunnyTrips — Data Flow Diagrams (DFD)

Gane & Sarson convention, Mermaid syntax.

**Paste-ready source:** use `DFD_MMD/*.mmd` (starts at `flowchart`, no markdown heading) in [mermaid.live](https://mermaid.live), then export for draw.io. Do not paste the `.md` H1 heading — that causes `UnknownDiagramError`. The `.md` files are documentation with the same diagrams fenced inside.

## Levels

| Level | File | Contents |
|---|---|---|
| 0 — Context | `00_level0_context.md` | System as single process `0.0` + external entities |
| 1 — Overview (full) | `01_level1_overview.md` | 12 major processes, data stores, all boundary flows (reference) |
| 1 — Overview (core) | `01_level1_core.md` | Critical spine: 1.0–8.0 + 10.0, stores D1–D7 + D9 — paper/print |
| 2 — Detail | `02_l2_*.md` | One zoom-in per Level 1 process (10 diagrams) |

Level 1 processes **9.0 Explore / Weather / Map** and **12.0 Notifications & Reports** have no Level 2 diagram.

**Paper/print:** use `01_level1_core.md` / `DFD_MMD/01_level1_core.mmd` (9 processes: 1.0–8.0 + 10.0, stores D1–D7 + D9). Full 12-process L1 is too dense for mermaid.live and print.

## Notation (Mermaid approximation)

| Element | Mermaid shape | Renders as |
|---|---|---|
| Process | `("1.0 Name")` | rounded rectangle |
| External entity | `["Name"]` | rectangle |
| Data store | `[("D1 Name")]` | cylinder (closest stand-in for open-end box) |
| Data flow | `A -- "label" --> B` | labeled arrow |

## External entities

- Guest Visitor
- Customer
- Admin
- Google Gemini API
- Stripe Payment Gateway
- OpenWeatherMap API
- SMTP Mail Server

## Data stores (shared across all levels)

| ID | Name | Core tables |
|---|---|---|
| D1 | Users, Admins & Notifications | `users`, `admins`, `notifications` |
| D2 | Catalog Inventory | `destinations`, `hotels`, `rooms`, `activities`, `add_ons`, `packages`, pivots |
| D3 | Vector Embeddings | `*.embedding`, `users.preferences_embedding` (pgvector) |
| D4 | Cart & Lucky Bundles | `cart_items` |
| D5 | Bookings & Manifests | `bookings`, `booking_items`, `booking_status_histories`, `passenger_category_rules` |
| D6 | Chat Sessions & Messages | `chat_sessions`, `chat_messages` |
| D7 | Support Inquiries | `support_inquiries` |
| D8 | Abuse & Moderation Logs | `chatbot_abuse_reports` |
| D9 | Reviews & Summaries | `reviews`, `review_summaries` |
| D10 | FAQs, Legal & Rules | `faqs`, `legal_documents`, `onboarding_options` |
| D11 | Weather Cache | `weather_caches` |

## Level 1 process map

| # | Process | L2 |
|---|---|---|
| 1.0 | Accounts, Auth & Onboarding | yes |
| 2.0 | Catalog Browse & Search | yes |
| 3.0 | Recommendations (DSS) | yes |
| 4.0 | Cart & Lucky Itinerary | yes |
| 5.0 | Checkout & Booking Request | yes |
| 6.0 | Payment Processing | yes |
| 7.0 | Booking Lifecycle (Admin + Expiry) | yes |
| 8.0 | Chatbot & Human Support | yes |
| 9.0 | Explore / Weather / Map | no |
| 10.0 | Reviews & Sentiment | yes |
| 11.0 | Content & Inventory Mgmt | yes |
| 12.0 | Notifications & Reports | no |
