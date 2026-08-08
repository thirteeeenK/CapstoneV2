# Task Checklist — Chatbot Implementation

- [x] 1. Migrations (chat_sessions, chat_messages — PostgreSQL)
- [x] 2. Models (ChatSession, ChatMessage — #[Fillable] attribute style)
- [x] 3. Services/Chat/IntentRouter (8 intents + constraint extraction)
- [x] 4. Services/Chat/ConversationManager (resolve/claim session, history, persist)
- [x] 5. Services/Chat/ChatbotService (orchestrator + per-intent handlers incl. itinerary)
- [x] 6. GeminiService additions (searchRoomsHybrid, generateChatResponse, buildItineraryContext)
- [x] 7. SystemPrompts/chatbot-system-prompt.md
- [x] 8. chat-guest rate limiter in AppServiceProvider + EnforceGuestChatLimits middleware
- [x] 9. ChatbotController + chatRoute.php + require in web.php
- [x] 10. chat-widget.blade.php (Alpine + thinking-orb + room/itinerary cards + guest-limit + session_token)
- [x] 11. Mount widget in frontend/layout.blade.php
- [x] 12. .env.example GEMINI_API_KEY + OPENWEATHER_API_KEY
- [x] 13. Tests (IntentRouterTest: 13/13 + ChatbotTest: 13/13 — Gemini faked via Http::fake)
- [x] 14. php artisan migrate + verify
- [x] 15. composer test (142/153 pass, 11 pre-existing Auth/Profile failures unchanged)

Note: 11 pre-existing Auth/Profile scaffold tests fail because CheckUserOnboarding redirects factory users to /onboarding (expected per AGENTS.md). IntentRouterTest: 13/13 passing. ChatbotTest: 13/13 passing.
