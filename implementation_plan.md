# Chatbot Implementation Plan

## Architecture
Hybrid Retrieval-Augmented Generation with 8 intents: GENERAL_TALK, ROOM_SEARCH, HOTEL_SEARCH, ACTIVITY_SEARCH, ITINERARY_QUERY, AVAILABILITY_QUERY, MAP_QUERY, WEATHER_QUERY. PostgreSQL sessions, Gemini 2.5-flash-lite for generation, text-embedding-001 for vectors. Web route `POST /chat`, session auth + CSRF.

## Files to create

### Migrations
- `database/migrations/YYYY_MM_DD_HHMMSS_create_chat_sessions_table.php` [NEW]
- `database/migrations/YYYY_MM_DD_HHMMSS_create_chat_messages_table.php` [NEW]

### Models
- `app/Models/ChatSession.php` [NEW] — #[Fillable], hasMany ChatMessage, belongsTo User, claimFor(), generateToken()
- `app/Models/ChatMessage.php` [NEW] — #[Fillable], belongsTo ChatSession

### Services
- `app/Services/Chat/IntentRouter.php` [NEW] — classify(), extractConstraints()
- `app/Services/Chat/ConversationManager.php` [NEW] — resolveSession(), history(), persist()
- `app/Services/Chat/ChatbotService.php` [NEW] — handle() orchestrator, per-intent handlers
- `app/Services/SystemPrompts/chatbot-system-prompt.md` [NEW]

### HTTP
- `app/Http/Middleware/EnforceGuestChatLimits.php` [NEW]
- `app/Http/Controllers/ChatbotController.php` [NEW]
- `routes/chatRoute.php` [NEW]

### Views
- `resources/views/components/frontend/chat-widget.blade.php` [NEW]

### Tests
- `tests/Unit/IntentRouterTest.php` [NEW]
- `tests/Feature/ChatbotTest.php` [NEW]

## Files to modify
- `app/Providers/AppServiceProvider.php` [MODIFY] — add chat-guest rate limiter
- `app/Http/Kernel.php` [MODIFY] — register EnforceGuestChatLimits middleware alias (if needed)
- `app/Services/GeminiService.php` [MODIFY] — add searchRoomsHybrid(), generateChatResponse(), buildItineraryContext()
- `routes/web.php` [MODIFY] — require chatRoute.php
- `resources/views/components/frontend/layout.blade.php` [MODIFY] — mount chat widget
- `.env.example` [MODIFY] — add GEMINI_API_KEY, OPENWEATHER_API_KEY placeholders
- `AGENTS.md` [MODIFY] — update chatbot status

## Guest rate limiting
- Reuse existing `throttle:ai` on route (covers authed users)
- New `chat-guest` limiter in AppServiceProvider (5/min by IP)
- `EnforceGuestChatLimits` middleware: daily cap ~15 messages/IP; 429 JSON with login CTA
- Session survives login: widget stores session_token → server claims on next authed request
