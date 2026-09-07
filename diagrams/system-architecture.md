# SunnyTrips System Architecture

Mermaid source. Renders on GitHub / Notion / Obsidian. Or paste at https://mermaid.live → Export PNG/SVG.

```mermaid
flowchart LR
    subgraph CLIENT["CLIENT TIER"]
        direction TB
        Traveler["Traveler Browser<br/>Blade + Tailwind + Alpine.js<br/>Vite bundle"]
        AdminUI["Admin / Support Browser<br/>Admin panel • Support inbox<br/>polling, no WebSockets"]
        Widget["Chat widget<br/>session_token in localStorage<br/>history restore on refresh"]
        Leaflet["Leaflet map client<br/>OSM tiles"]
        Traveler --- Widget
        Traveler --- Leaflet
    end

    subgraph WEB["WEB TIER"]
        direction TB
        Routes["routes/web.php<br/>require per feature<br/>package • cart • checkout<br/>hotel • activity • chat<br/>dss • admin* • auth"]
        MW["Middleware<br/>auth • auth:admin<br/>CheckUserOnboarding<br/>EnforceGuestChatLimits<br/>verified • throttle"]
        Routes --> MW
    end

    subgraph APP["APPLICATION TIER - Laravel 13 / PHP 8.3"]
        direction TB
        Identity["Identity<br/>dual guards: auth + auth:admin<br/>Breeze • AdminModel"]
        Booking["Booking domain<br/>CartService • BookingRequestService<br/>PaymentService + drivers<br/>BookingExpiryService<br/>state machine"]
        DSS["DSS / Personalization<br/>Onboarding quiz → preferences_embedding<br/>rankRecommendations<br/>RecommendationExplainer<br/>tag-match fallback"]
        RAG["Conversational AI - internal RAG<br/>ChatbotService • IntentRouter<br/>ConversationManager • GeminiService<br/>hybrid search: pgvector SQL + PHP cosine<br/>SupportQueueService handoff"]
        AdminOps["Admin & Ops<br/>inventory • bookings • reports<br/>FAQ • AdminAuditService<br/>ProcessReviewSentimentJob<br/>UpdateEntityReviewSummaryJob"]
    end

    subgraph DATA["DATA TIER"]
        direction TB
        PG[("PostgreSQL + pgvector<br/>catalog: hotels room_types<br/>activities packages add_ons destinations<br/>booking: cart_items bookings booking_items<br/>chat: chat_sessions chat_messages support_inquiries<br/>prefs: users + preferences_embedding<br/>user_preferences + explanations jsonb<br/>ops: reviews faqs admins audits<br/>webhook_events • jobs queue")]
        Store["File storage<br/>storage/app images<br/>asset-storage"]
    end

    subgraph EXT["EXTERNAL SERVICES"]
        direction TB
        GeminiAPI[(Google Gemini API<br/>text-embedding-001<br/>gemini-2.5-flash-lite)]
        Weather[(OpenWeatherMap)]
        Pay[(Payment Gateway)]
        Mail[(SMTP mail)]
        Tiles[(OSM / Leaflet tiles)]
    end

    Traveler -->|"HTTPS<br/>HTTP Request / Response"| Routes
    AdminUI -->|"HTTPS<br/>auth:admin"| Routes
    MW --> Identity
    MW --> Booking
    MW --> DSS
    MW --> RAG
    MW --> AdminOps

    Booking -->|"Eloquent"| PG
    DSS -->|"Eloquent + pgvector <=>"| PG
    RAG -->|"Eloquent + hybrid search"| PG
    Identity -->|"Eloquent"| PG
    AdminOps -->|"Eloquent + queue:listen<br/>database driver"| PG
    AdminOps -->|"read / write images"| Store

    RAG -->|"HTTPS / JSON<br/>embed + chat"| GeminiAPI
    DSS -->|"HTTPS / JSON<br/>embed + explainer"| GeminiAPI
    AdminOps -->|"HTTPS / JSON"| Weather
    Booking -->|"HTTPS / JSON<br/>sessions + webhooks"| Pay
    Pay -.->|"webhook + signature<br/>PaymentWebhookController"| Booking
    Booking -->|"SMTP<br/>Received Paid Approved<br/>Rejected Cancelled Expired"| Mail
    Leaflet -->|"HTTPS tiles"| Tiles
```

## Component traceability

| Diagram box         | Real source                                                                                                                                                         |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Routes + middleware | `routes/web.php`, `routes/*Route.php`, `CheckUserOnboarding`, `EnforceGuestChatLimits`                                                                              |
| Identity            | `AdminModel`, `auth:admin` guard, Breeze `Auth/*` controllers                                                                                                       |
| Booking domain      | `CartService`, `BookingRequestService`, `PaymentService` + Stripe/QRPH/Simulator drivers, `PaymentWebhookController`, `BookingExpiryService`                        |
| DSS                 | `RecommendationController`, `OnboardingController`, `GeminiService::rankRecommendations`, `RecommendationExplainer`, `user_preferences.recommendation_explanations` |
| RAG (internal)      | `Services/Chat/ChatbotService`, `IntentRouter`, `ConversationManager`, `SupportQueueService`, `chat_sessions.session_token`                                         |
| Admin & Ops         | `Admin/*` controllers, `AdminAuditService`, `ProcessReviewSentimentJob`, `UpdateEntityReviewSummaryJob`                                                             |
| Data                | PostgreSQL + pgvector `vector(3072)`, `jobs` table (database queue), `storage/app` images                                                                           |
| External            | `config/services.php` (gemini, stripe, qrph/paymongo, openweather), SMTP mailers, OSM tiles                                                                         |
