# SunnyTrips Capstone: Decision Support System (DSS) Overview

This document outlines how the SunnyTrips Capstone project transitions from a standard information system to a true Decision Support System (DSS) through its map, weather, and chatbot components.

## 1. How Map and Weather Components Make it a DSS

A standard information system simply _presents_ data (e.g., showing a map with pins, or displaying "It is 30°C and raining"). A **Decision Support System** goes a step further: it _analyzes_ that data, combines it with other variables, and helps the user evaluate options to make an informed choice.

To qualify as a DSS, these components provide the following decision-making capabilities:

- **Multi-Criteria Decision Analysis (MCDA):** The system does not just filter by a single metric. It combines data. For example, the map (location) + weather + user preferences (budget, interests) work together to rank available options.
- **Risk Mitigation / "What-If" Scenarios:** The weather component acts as a DSS when it helps users avoid bad decisions. If a user plans an outdoor activity while severe weather is forecasted, the system flags this risk and suggests safer alternatives.
- **Spatial Decision Support (SDSS):** The map becomes a DSS by aiding with spatial logic and routing. Instead of merely showing _where_ locations are, it helps the user decide _which_ destination to choose based on proximity to their current itinerary, traffic, or transportation.

## 2. Location-Based Chatbot Interactions

The chatbot serves as a highly effective **conversational DSS interface** when it can handle location-based queries such as:

- _"Which hotel is nearest to me?"_
- _"What is the distance between my location and Hotel A?"_
- _"Which tourist attraction is closest to my current location?"_

**Technical Implementation Requirements:**

- **Geolocation Data:** The frontend captures the user's current coordinates (with permission) and passes them to the backend/chatbot engine.
- **Spatial Calculations:** The backend logic utilizes spatial formulas (e.g., the Haversine formula) to calculate real-time distances between the user's coordinates and the locations in the database.
- **Decision Output:** The bot _decides_ the best options by sorting these distances and presenting the top results with contextual explanations (e.g., "The nearest is Hotel A, which is 2km away").

## 3. Weather-Based AI Decision Support

Allowing users to ask weather-related queries shifts the chatbot from a basic FAQ tool to an active **rule-based/AI-driven decision agent**. Examples include:

- _"Is today a good day to visit the beach?"_
- _"Which tourist attractions are recommended based on today's weather?"_
- _"Should I postpone my trip because of the forecast?"_

**Technical Implementation Requirements:**

- **Attribute Tagging:** Database records for attractions must include tags classifying their environment (e.g., `indoor`, `outdoor`, `water-activity`, `museum`).
- **Logic Mapping:** The system requires rules to evaluate the current weather forecast against these tags.
    - _Example:_ If `weather = Heavy Rain`, apply a negative weight to `outdoor` attractions and a positive weight to `indoor` attractions.
- **Explainable Recommendations:** A defining feature of a DSS is explainability. The AI must explain _why_ it recommends an action. (e.g., _"Since it is raining heavily today, I advise against visiting the beach. Instead, I recommend visiting the City Museum."_)

## 4. Adaptive & Personalized Decision Support (Itinerary Generation)

A hallmark of an advanced DSS is **adaptive personalization**. Instead of just answering one-off questions, the system can understand the user's broader goal (e.g., planning a full day) and adapt over time.

For example, if a user asks, _"Can you create a 2-day itinerary for a family of 4 focusing on outdoor activities?"_

The chatbot can function as an **automated planning agent** by:

1.  **Synthesizing Constraints:** Factoring in the user's group size, preference (outdoor), budget (if known), current weather, and the geographic proximity of locations.
2.  **Generating the Plan:** Outputting a structured daily schedule (Morning, Afternoon, Evening).
3.  **Actionable Outputs (Saving the Itinerary):** A true DSS must provide actionable outputs. Rather than forcing the user to copy-paste the chat text, the chatbot should return a structured response (like a JSON payload or a specific UI component) that allows the frontend to render a **"Save Itinerary to Profile"** button. Clicking this button would save the generated plan directly to the user's database records (e.g., an `itineraries` table), allowing them to view, edit, or share it later.

## 5. Conversational Elicitation (Handling Missing Constraints)

A key function of a DSS is helping users structure their problems. Often, users do not provide all the necessary parameters upfront.

**Example Scenario: Budget-Constrained Itinerary Planning**
If a user prompts: _"Create me an itinerary with a budget of ₱20,000,"_ the AI should not make wild assumptions. Instead, it utilizes **Mixed-Initiative Interaction** (where the AI takes the lead to ask questions).

**Step-by-Step Flow:**
1. **User Request:** _"Create me an itinerary with a budget of ₱20,000."_
2. **System Prompt Rules Trigger:** The AI's hidden system instructions state: _"Never generate an itinerary without knowing the number of travelers, trip duration, and interests."_
3. **AI Follow-Up:** The chatbot pauses generation and asks: _"I'd love to help you plan! To make sure it fits your ₱20,000 budget, could you tell me how many days your trip will be, how many people are traveling, and what kind of activities you enjoy?"_
4. **User Response:** _"It's just me for 3 days, I like outdoor activities."_
5. **AI Evaluation (Tool Calling):** The AI uses its internal tools to check the SunnyTrips database for flights, hotels, and outdoor activities, ensuring the total cost mathematically stays under ₱20,000.
6. **Final Output:** The AI presents a personalized 3-day itinerary that respects the budget constraint.

**Why this makes it a DSS:**
By actively eliciting missing variables, the system ensures the final decision output is highly precise and tailored to the user's actual needs, rather than relying on flawed defaults. This conversational back-and-forth is the correct, standard approach for building an intelligent decision support agent.

## 6. Conversation Context Memory (Stateful Interactions)

For an AI to act as a seamless decision support agent, it must remember the context of the conversation. It needs to understand pronouns or implicit references based on what was discussed previously.

**How it Works (Message History Array):**
AI models do not inherently "remember" users across API calls. Instead, the SunnyTrips application must store the chat history and send the entire transcript back to the AI with every new message. This allows the AI to read the previous lines and understand the current context.

**Step-by-Step Flow:**
1. **User Request:** _"Tell me about Shangri-La Boracay."_
2. **Data Sent to AI:** `[User: "Tell me about Shangri-La Boracay."]`
3. **AI Response:** _"Shangri-La Boracay is a luxury resort located in..."_
4. **User Follow-Up:** _"Okay, what is the price?"_ (Notice the user doesn't mention the hotel name again).
5. **Data Sent to AI (The crucial step):** The application sends the full history array: 
   `[User: "Tell me...", AI: "Shangri-La Boracay is...", User: "Okay, what is the price?"]`
6. **AI Contextual Understanding:** Because the AI reads the previous messages in the array, it perfectly understands that "the price" refers to Shangri-La Boracay and queries the database accordingly.

**Why this makes it a DSS:**
Maintaining context allows the user to explore decisions naturally without repeating themselves, mirroring a real consultation with a human travel agent. This reduces cognitive load and makes data exploration more intuitive.

## 7. Multi-Step Conversational Booking (Intelligent Intermediary)

A DSS does not have to act completely autonomously; it often serves as a bridge between the user and human administrators. Handling a booking request through a multi-step conversation is not only possible but highly recommended.

When a user says _"Book me a trip"_, the chatbot can act as a **Conversational Agent with State Management**. It tracks which pieces of information (slots) are missing and systematically asks for them over multiple chat turns:

- Travel party size and details
- Hotel and activity preferences
- Dates
- Transportation/flight needs

Once all information is gathered, the chatbot generates a structured summary and submits it to an administrator dashboard (e.g., as a "Pending Booking Request").

**Why this fits the DSS Architecture:**
This setup functions as **Workflow Automation** and an **Intelligent Intermediary**. By processing unstructured natural language into a structured booking request, the AI significantly reduces the cognitive load on both the user and the administrator. It qualifies as a DSS because it organizes the complex logistics of trip planning into a manageable format, leaving the final confirmation and execution to a human—which is a standard and highly practical model in the tourism industry.

## 8. Advanced DSS Features (System Improvements)

To elevate the system into a truly robust, enterprise-grade DSS, the following capabilities should be integrated into the chatbot's logic:

### A. Real-Time Resource Availability (Database Integration)

A recommendation is only useful if it is actionable. The chatbot must cross-reference its recommendations with actual database availability in real-time.

- **Implementation:** Before recommending a hotel or tour based on proximity and weather, the AI must check the database (e.g., querying the `rooms` or `bookings` table) to verify capacity for the requested dates.
- **DSS Value:** If a hotel is fully booked, the AI dynamically recommends the _next best_ option and explains the constraint: _"Hotel A is the closest to your location, but it is fully booked for your dates. I recommend Hotel B instead, which is available."_ This ensures the DSS only provides viable solutions.

### B. Confidence Scoring & Explainability

In advanced Decision Support Systems, the system provides options with a transparent probability or confidence score rather than absolute answers.

- **Implementation:** When suggesting an itinerary or hotel, the chatbot can calculate and display a "Match Score" (e.g., _95% Match_). This score is derived by evaluating how many of the user's constraints were successfully met (e.g., budget met, weather optimal, but slightly further distance).
- **DSS Value:** Providing a confidence score adds transparency to the AI's reasoning, allowing the user to make a highly informed final decision based on the system's mathematical evaluation.

---

### Conclusion: The Chatbot as a DSS Interface

If the chatbot only answers basic queries like "What time does Hotel A open?", it functions merely as a standard FAQ bot. However, by synthesizing live context (weather APIs), spatial data (maps/GPS), and user constraints (natural language queries) to generate **personalized, synthesized, and explainable recommendations**, the chatbot successfully acts as the natural language interface for the underlying Decision Support System engine.
