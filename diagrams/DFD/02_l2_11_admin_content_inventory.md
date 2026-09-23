# DFD Level 2 — Process 11.0 Content and Inventory Management

Parent: Level 1 process `11.0`. Admin CRUD for catalog, FAQs, legal, onboarding options, passenger rules; re-embed trigger; abuse moderation.

```mermaid
flowchart TB
    ADMIN["Admin"]
    GEMINI["Google Gemini API"]
    P2["2.0 Catalog Browse and Search"]
    P8["8.0 Chatbot and Human Support"]

    P111("11.1 Destination Hotel Room CRUD")
    P112("11.2 Activity Add-on Package CRUD")
    P113("11.3 Visibility and Inventory Flags")
    P114("11.4 FAQ Legal and Onboarding Options")
    P115("11.5 Passenger Rules and User Moderation")
    P116("11.6 Re-embed Catalog Items")

    D1[("D1 Users Admins Notifications")]
    D2[("D2 Catalog Inventory")]
    D3[("D3 Vector Embeddings")]
    D8[("D8 Abuse and Moderation Logs")]
    D10[("D10 FAQs Legal and Rules")]

    ADMIN -- "create / update / delete destinations hotels rooms" --> P111
    P111 -- "write catalog tables" --> D2
    ADMIN -- "create / update activities add-ons packages" --> P112
    P112 --> D2

    ADMIN -- "toggle is_shown / inventory counts" --> P113
    P113 --> D2
    D2 -- "live catalog for browse" --> P2

    ADMIN -- "manage FAQ / legal documents / onboarding options" --> P114
    P114 -- "write content tables" --> D10
    D10 -- "FAQ grounding for chatbot" --> P8

    ADMIN -- "edit passenger rules / ban or warn users / audit abuse" --> P115
    P115 -- "write passenger_category_rules" --> D1
    P115 -- "update user ban fields" --> D1
    P115 -- "moderate abuse reports" --> D8

    ADMIN -- "save item then trigger embed all or force embed" --> P116
    P116 -- "send text content" --> GEMINI
    GEMINI -- "3072-dim vectors" --> P116
    P116 -- "write embedding columns" --> D3
    P116 -- "keep catalog denormalized copy in sync" --> D2
    D3 -- "vectors for DSS and chat RAG" --> P8
```
