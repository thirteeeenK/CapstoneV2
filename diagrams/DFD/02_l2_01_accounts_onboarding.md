# DFD Level 2 — Process 1.0 Accounts, Auth and Onboarding

Parent: Level 1 process `1.0`. Balances with flows: Guest/Customer auth + preferences in; profile/vector out to D1, P3, P4; Gemini embed; D10 option reads.

```mermaid
flowchart TB
    GUEST["Guest Visitor"]
    CUST["Customer"]
    GEMINI["Google Gemini API"]

    P11("1.1 Register and Login")
    P12("1.2 Profile and Settings")
    P13("1.3 Onboarding Quiz Capture")
    P14("1.4 Embed and Aggregate Preference Vector")

    D1[("D1 Users Admins Notifications")]
    D3[("D3 Vector Embeddings")]
    D10[("D10 FAQs Legal and Rules")]

    GUEST -- "register / login credentials" --> P11
    P11 -- "create or authenticate user" --> D1
    D1 -- "session / profile" --> P12
    CUST -- "edit profile / password / delete account" --> P12
    P12 --> D1

    CUST -- "vibes / amenities / destination / traveler type" --> P13
    D10 -- "admin-managed onboarding options" --> P13
    P13 -- "raw preference selections" --> P14
    P14 -- "embed selection text" --> GEMINI
    GEMINI -- "3072-dim vectors" --> P14
    P14 -- "write users.preferences_embedding and user_preferences" --> D1
    P14 -- "store aggregate vector copy" --> D3
    D1 -- "completed profile + vector" --> CUST
    D1 -- "user vector for recommendations" --> P3["3.0 Recommendations DSS"]
    D1 -- "user vector for lucky cart" --> P4["4.0 Cart and Lucky Itinerary"]
```
