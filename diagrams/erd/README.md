# SunnyTrips Mermaid.js ERD Documentation

This folder contains all 100% schema-verified Entity-Relationship Diagrams (ERD) written in **Mermaid.js** format for SunnyTrips.

## Files Index

| File | Subsystem | Content |
|---|---|---|
| [`erd_full.mmd`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_full.mmd) / [`erd_full.md`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_full.md) | **Full System ERD** | All 22 domain database tables, exact column types, PK/FK/UK constraints, and relationships. |
| [`erd_a_catalog_reviews.mmd`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_a_catalog_reviews.mmd) / [`erd_a_catalog_reviews.md`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_a_catalog_reviews.md) | **ERD-A** | Catalog & Reviews (`destinations`, `hotels`, `rooms`, `activities`, `add_ons`, `packages`, `reviews`, `review_summaries`). |
| [`erd_b_booking_payment.mmd`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_b_booking_payment.mmd) / [`erd_b_booking_payment.md`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_b_booking_payment.md) | **ERD-B** | Booking & Payment (`users`, `admins`, `cart_items`, `bookings`, `booking_items`, `booking_status_history`, `passenger_category_rules`, `reviews`). |
| [`erd_c_chat_support.mmd`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_c_chat_support.mmd) / [`erd_c_chat_support.md`](file:///c:/xampp/htdocs/SunnyTripsCapstoneV2/diagrams/erd/erd_c_chat_support.md) | **ERD-C** | Chatbot & Support Queue (`users`, `admins`, `chat_sessions`, `chat_messages`, `support_inquiries`, `chatbot_abuse_reports`, `faqs`, `weather_cache`, `legal_documents`). |

---

## How to View / Import Mermaid ERDs

1. **GitHub / VS Code Markdown Preview:** Open any `.md` file in this directory to render interactive diagrams natively.
2. **Mermaid Live Editor:** Copy contents of any `.mmd` file into [mermaid.live](https://mermaid.live).
3. **Draw.io:** Go to `Arrange` -> `Insert` -> `Advanced` -> `Mermaid`, paste the code, and click `Insert`.
