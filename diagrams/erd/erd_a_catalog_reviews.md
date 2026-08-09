# ERD-A — Catalog & Reviews Subsystem

```mermaid
erDiagram
    destinations ||--o{ hotels : "contains"
    destinations ||--o{ activities : "contains"
    destinations ||--o{ add_ons : "contains"
    destinations ||--o{ packages : "contains"

    hotels ||--o{ rooms : "offers"

    hotels ||..o{ reviews : "morph_reviewed"
    rooms ||..o{ reviews : "morph_reviewed"
    activities ||..o{ reviews : "morph_reviewed"
    packages ||..o{ reviews : "morph_reviewed"

    hotels ||..o{ review_summaries : "morph_summarized"
    activities ||..o{ review_summaries : "morph_summarized"
    packages ||..o{ review_summaries : "morph_summarized"

    destinations {
        bigint id PK
        string name
        string region
        text description
        string image
        decimal latitude
        decimal longitude
    }

    hotels {
        bigint id PK
        bigint destination_id FK
        string hotel_name
        string type
        json vibe_tags
        json featured_amenities
        text hotel_description
        string specific_address
        decimal latitude
        decimal longitude
        boolean is_shown
        vector_3072 embedding
    }

    rooms {
        bigint id PK
        bigint hotel_id FK
        string room_name
        string view_type
        string ideal_for
        int total_rooms
        int occupancy
        int base_occupancy
        int max_occupancy
        string bed_configuration
        string room_size
        json room_amenities
        decimal base_price
        decimal extra_person_fee
        json images
        boolean is_shown
        vector_3072 embedding
    }

    activities {
        bigint id PK
        bigint destination_id FK
        string activity_name
        string category
        string activity_level
        string rate
        string duration
        string capacity
        text requirements
        string ideal_for
        json vibe_tags
        json inclusions
        json exclusions
        json itinerary
        text notes
        json images
        decimal latitude
        decimal longitude
        boolean is_shown
        vector_3072 embedding
    }

    add_ons {
        bigint id PK
        bigint destination_id FK
        string name
        string type
        text description
        json inclusions
        json pricing_tiers
        json surcharges
        boolean is_shown
        vector_3072 embedding
    }

    packages {
        bigint id PK
        bigint destination_id FK
        string name
        string type
        decimal price
        int days
        int nights
        int min_pax
        date valid_from
        date valid_to
        json generic_inclusions
        json images
        boolean is_active
        vector_3072 embedding
    }

    reviews {
        bigint id PK
        bigint booking_id FK
        bigint booking_item_id FK
        bigint user_id FK
        string reviewer_name
        string reviewable_type
        bigint reviewable_id
        bigint hotel_id
        bigint room_id
        bigint activity_id
        bigint package_id
        int rating
        text comment
        string sentiment
        decimal sentiment_score
        jsonb extracted_keywords
        boolean is_verified_booking
        boolean is_published
        boolean is_featured
    }

    review_summaries {
        bigint id PK
        string summarizable_type
        bigint summarizable_id
        int total_reviews
        decimal average_rating
        decimal positive_percentage
        decimal neutral_percentage
        decimal negative_percentage
        text ai_summary_text
        jsonb top_positive_highlights
        jsonb top_negative_highlights
        jsonb most_frequent_keywords
        timestamp last_analyzed_at
    }
```
