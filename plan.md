# SunnyTrips 48-Hour Hackathon Sprint Plan

This is a prioritized, step-by-step battle plan to successfully build and integrate the Core System and the AI Decision Support System (DSS) components in 2 days for your capstone defense.

---

## 🎯 Day 1: Foundation & Core DSS Features

### Phase 1: Core Foundation & Data Seeding (Hours 1 - 8)

_Goal: Get the basic system running so the AI has actual data to analyze._

- [ ] **User Onboarding:** Finish the Registration and Login flows. Ensure sessions/authentication are working.
- [ ] **Core UI & CRUD:** Ensure users can view Hotels, Activities, and Rooms. Don't worry about pixel-perfect design yet; use a component library (Bootstrap/Tailwind) to keep it clean and fast.
- [ ] **Database Seeding (Crucial):** You need data to prove your DSS works. Use an AI to generate a SQL script containing:
    - 50 dummy users.
    - 10-15 Hotels/Activities with coordinates (latitude/longitude).
    - 100+ realistic reviews (mix of positive, negative, and mixed feedback). 

### Phase 2: Map & Spatial DSS (Hours 9 - 16)

_Goal: Implement the visual map and distance-based decision logic._

- [ ] **Map Integration:** Integrate a map library (e.g., Leaflet.js or Google Maps API) on the frontend.
- [ ] **Plotting Data:** Query your database and drop pins for all hotels and activities on the map.
- [ ] **Geolocation & Spatial Logic:** Add a "Find Nearest to Me" button.
    - Capture the user's current GPS coordinates via the browser.
    - Calculate the distance using the Haversine formula (or a database spatial query).
    - Display the options sorted by proximity.

### Phase 3: Weather Integration DSS (Hours 17 - 24)

_Goal: Implement real-time risk mitigation and context-aware suggestions._

- [ ] **API Integration:** Connect to a free weather API (like OpenWeatherMap or WeatherAPI) to get the current forecast for the destination.
- [ ] **Rule-Based Decision Logic:** Add tags to your activities (e.g., `outdoor`, `indoor`).
- [ ] **Frontend Alerts:** Write logic that says: _IF weather == "Rain" AND activity_type == "Outdoor" THEN display a warning alert on the activity page._ (e.g., > [!WARNING] Rain is forecasted. Consider indoor activities today.)

---

## 🚀 Day 2: Advanced AI & Integration

### Phase 4: Sentiment Analysis & Summarization (Hours 25 - 32)

_Goal: Implement the "E-commerce Style" review summaries to prevent Information Overload._

- [ ] **API Setup:** Set up your OpenAI or Google Gemini API keys in your `.env` file.
- [ ] **Batch Processing Script:** Write a backend script that loops through all your newly seeded reviews.
    - Send the reviews for a specific hotel to the LLM.
    - Ask the LLM to return a 1-100 `sentiment_score` and a 2-sentence `ai_summary`.
    - Save these results into your `hotels` database table.
- [ ] **UI Update:** Update the frontend listing pages to prominently display this AI Summary and Score directly above the raw user reviews.

### Phase 5: RAG Chatbot Integration (Hours 33 - 42)

_Goal: Build the ultimate conversational decision agent that ties everything together._

- [ ] **Chat UI:** Build a simple chat window component that floats in the corner of the app.
- [ ] **Context Injection (The Brains):** When a user types a message, your backend should dynamically fetch relevant context before sending the prompt to the AI. This includes:
    - The current Weather data.
    - The user's current location/coordinates.
    - Database queries (e.g., pulling the `ai_summary` if they ask about a specific hotel).
- [ ] **System Prompting:** Write a strict system prompt forcing the chatbot to:
    - Ask follow-up questions for missing constraints (Budget, Pax, Dates).
    - Generate personalized itineraries mathematically respecting the budget constraint.
    - Embed the retrieved review summaries into its conversational responses.

### Phase 6: Buffer, Testing, & Panel Prep (Hours 43 - 48)

_Goal: Polish the MVP and ensure the demonstration flows flawlessly._

- [ ] **End-to-End Walkthrough:** Pretend to be a user and click through the entire flow. Find bugs and patch them.
- [ ] **Script the Demo:** Do not improvise your presentation! Write down the exact questions you will ask the chatbot during the defense so you know the AI will output the perfect answer based on your seeded data.
- [ ] **Sleep!** You need a clear head to defend the architecture.
