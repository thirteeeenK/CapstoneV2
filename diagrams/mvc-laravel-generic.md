# Laravel MVC Architecture — Generic

Mermaid source. Renders on GitHub / Notion / Obsidian. Or paste at https://mermaid.live → Export PNG/SVG.

```mermaid
flowchart TB
    B([Browser]) --> R["routes/web.php"]
    R --> M["Middleware"]
    M --> C["Controller<br/>translate, delegate, respond"]
    C --> S["Service / Action<br/>all business logic"]
    S --> E["Model<br/>relationships, casts, scopes"]
    E <--> DB[("Database")]
    C --> V["View - Blade"]
    V --> B
```
