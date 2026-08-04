# Package System Implementation Plan (Approach B)

Ang planong ito ay nagdedetalye kung paano natin i-se-setup ang Database at Models para sa **Dynamic Package System**. Dahil Approach B ang napili, naka-link na ang packages sa Hotels, Activities, at Destinations.

## Proposed Database Schema

Gagawa tayo ng main table para sa Packages, at dalawang _Pivot Tables_ para ma-connect natin sila sa existing Hotels at Activities nang dynamic.

### 1. `packages` (Main Table)

Dito naka-store yung pinaka-details ng promo.

- `id` (Primary Key)
- `destination_id` (Foreign Key -> naka-link sa kung anong lugar, e.g., Boracay, El Nido)
- `name` (e.g., "Boracay Tipid Deal")
- `type` (e.g., "Flight + Hotel + Transfer")
- `price` (e.g., 6999)
- `days` (e.g., 3)
- `nights` (e.g., 2)
- `min_pax` (e.g., 2)
- `valid_from` & `valid_to` (Para sa validity periods tulad ng "August - December 2025")
- `generic_inclusions` (JSON) - Dito natin ilalagay as text array yung mga generic na inclusions tulad ng `["Terminal Fee", "Environmental Fee", "Roundtrip Airfare", "Van and Boat Transfer"]` dahil hindi naman sila kailangan ng sariling table.
- `images` (JSON) - Array ng image paths para sa banner.
- `is_shown` (Boolean) - Default `true`. Public visibility flag para pwedeng i-hide/show ang package sa user-facing website at Central Inventory Management.
- `embedding` (Text / JSON / Vector) - Vector embedding para sa AI Chatbot & RAG semantic recommendation engine.

### 2. `package_hotel` (Pivot Table)

Para ma-link ang package sa specific na Hotel. Kung sakaling gusto ng admin na mag-alok ng choices of hotels para sa iisang package.

- `id`
- `package_id`
- `hotel_id`

### 3. `package_activity` (Pivot Table)

Para ma-link ang package sa mga activities. Halimbawa sa "Best Deal", naka-link dito yung "Island Hopping w/ Lunch Buffet" na kukunin natin sa `ActivityModel`.

- `id`
- `package_id`
- `activity_id`

## Central Inventory Management & Public Visibility Integration

Dahil ang Central Inventory (`/admin/inventory`) ang nagsisilbing command center para sa visibility ng buong SunnyTrips ecosystem (Hotels, Rooms, Activities, Add-ons/Transfers):

1. **Visibility Controls (`is_shown`)**:
    - Magkakaroon ang `packages` table ng `is_shown` column (boolean, default: `true`).
    - Pwedeng i-toggle ng Admin ang public visibility ng anumang package sa Central Inventory via AJAX/Form toggle (`type=package`).

2. **Central Inventory Dashboard (`/admin/inventory`) Integration**:
    - **Packages Stat Card**: Mag-a-add ng stat card sa top overview showing total packages, count of visible packages, at count of hidden packages.
    - **Packages Tab**: Mag-a-add ng dedicated navigation tab (`Packages Inventory`) katabi ng Hotels, Rooms, Activities, at Add-ons.
    - **Packages Management Table**: Lalabas dito ang Package Name, Destination, Price, Validity, Public Visibility Toggle Button (`Visible to Users` / `Hidden from Users`), at Direct Edit Action button.

3. **AI Search & RAG Integration (`GeminiService`)**:
    - Magkakaroon ng `buildPackageEmbeddingText()` sa `GeminiService` para ma-generate ang vector embeddings ng packages.
    - Ang AI Chatbot ay magre-recommend lamang ng packages na nakamarkang `is_shown = true`.

## Bakit Maganda Ang Setup Na Ito?

1. **Dynamic Display sa Frontend**: Sa UI mo, pwede mong i-loop yung `$package->generic_inclusions` para lumabas yung mga checkmarks. Tapos pwede mong i-loop yung `$package->activities` para makita ng user yung pictures ng mismong island hopping na kasama sa package.
2. **Flexible sa Admin**: Sa Admin Panel, may form kung saan ttype lang nila yung details, tapos may multi-select dropdown para sa Hotels at Activities na isasama nila sa package.
3. **Unified Visibility Control**: Isang pindot lang sa Central Inventory Dashboard, agad na ma-i-t-hide o ma-i-s-show ang buong Tour Package sa website at sa AI recommendation chatbot.

## Next Steps para bukas:

1. I-ge-generate natin ang mga Migration files para sa tatlong tables na ito (`packages`, `package_hotel`, `package_activity`).
2. I-se-setup natin ang `Package` model at ang mga Relationships (`belongsTo`, `belongsToMany`).
3. I-e-extend natin ang `InventoryController` at `admin/inventory/index.blade.php` para kasama na ang Packages Inventory tab at visibility toggle.
4. Gagawa tayo ng Seeder para magkaroon ka agad ng dummy data na kamukha nung nasa poster para ma-test mo agad sa UI!
