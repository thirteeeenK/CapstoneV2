# DSS API Integration Guide: Weather & Maps

This document outlines the strategy for integrating third-party APIs (OpenWeatherMap and Leaflet.js) into the SunnyTrips Decision Support System (DSS). It addresses tier limits, cost-saving architectures, and how these tools function as decision agents.

---

## 1. OpenWeatherMap Integration Strategy

Weather data is crucial for risk mitigation (e.g., advising users against outdoor activities during typhoons). The system utilizes OpenWeatherMap for this data.

### A. The Free Tier 5-Day Forecast Clarification
If the defense panel asks how the system affords a 5-day forecast without a premium subscription, you can explain:

> _"We are utilizing the standard **5-Day/3-Hour Forecast API (v2.5)** provided by OpenWeatherMap. While their newer One Call API 3.0 and 16-day forecasts require a credit card or subscription, the v2.5 endpoint is permanently included in their Free Tier. It provides highly accurate 5-day predictive data in 3-hour increments, which is more than enough data to run our DSS rules."_

### B. API Optimization Architecture (Caching)
The Free Tier is limited to **1,000 API calls per day**. If the API is called via JavaScript every time a user loads a page, the limit will be exhausted immediately, causing the system to crash.

To prevent this, the system implements **Server-Side Caching**:

1. **Backend Fetching:** The frontend never calls OpenWeather directly. Instead, the Laravel backend makes the HTTP request.
2. **Database/Cache Storage:** When the backend receives the weather data, it saves it in the local cache or database for a set duration (e.g., 3 hours). Weather patterns do not change fast enough to warrant per-minute updates.
3. **Serving Cached Data:** When subsequent users load the page, the backend serves the cached weather data instead of making a new API call to OpenWeather.
4. **Location Grouping:** Instead of fetching weather for individual hotels, the system fetches weather based on the overarching City or Island (e.g., "Boracay").

**The Result:** By caching a city's weather for 3 hours, the system only makes **8 API calls per city, per day**, easily keeping the application well under the 1,000 call limit regardless of user traffic.

---

## 2. Leaflet.js Map Integration Strategy

To fulfill the **Spatial Decision Support System (SDSS)** requirement, the application must plot locations and help users make proximity-based decisions. 

The system utilizes **Leaflet.js** instead of Google Maps.

### A. Why Leaflet.js?
If the panel asks why Google Maps was not used, this is the strategic defense:

> _"We chose Leaflet.js because it is a lightweight, open-source JavaScript library that is completely free and does not require credit card billing integrations, unlike the Google Maps API. We paired it with OpenStreetMap (OSM) tile layers. This ensures our capstone project remains highly scalable and deployment-ready without incurring unexpected third-party API debts."_

### B. How Leaflet acts as a Spatial DSS
Displaying a map is just an information system. Leaflet transforms into a **Decision Support System** through the following implementations:

1. **Visual Data Aggregation (Markers):** The backend queries the database for all valid hotels and activities, retrieving their `latitude` and `longitude` coordinates. Leaflet drops visual pins for all these entities, allowing the user to visually analyze clustering (e.g., seeing which hotels are closest to the beach activities).
2. **Geolocation Interactivity:** The system utilizes HTML5 Geolocation to capture the user's current GPS coordinates (with permission). Leaflet plots the user's location on the map relative to the entities.
3. **Distance Calculation (The Haversine Formula):** The DSS calculates the actual distance between the user's coordinate and the hotel coordinates. It then sorts the recommendations and outputs actionable advice (e.g., _"Hotel A is the best choice based on your location, as it is only 1.2km away."_).

---

## 3. Future Enhancements (Possible Changes)

To further elevate the Spatial Decision Support capabilities in the future, the system can implement a **Hybrid Routing Architecture**.

This architecture dynamically switches spatial algorithms based on the user's context:

*   **Macro-Level (City-to-City):** For long distances (e.g., a user in Bicol asking about Boracay), the system continues to use the **Haversine formula**. This provides a fast, straight-line approximation ("flight distance"), which is the most relevant metric for macro-level travel planning.
*   **Micro-Level (Intra-City):** For local, short-distance navigation (e.g., navigating from a chosen hotel to a nearby beach activity), straight-line distance is often inaccurate due to road networks and natural barriers. In the future, the system can integrate a **Routing API** (such as OpenRouteService or Leaflet Routing Machine). This allows the chatbot and the map to provide highly accurate, actual driving/walking distances and estimated travel times.
