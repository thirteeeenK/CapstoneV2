# Package System Implementation Plan (Approach B)

Ang planong ito ay nagdedetalye kung paano natin i-se-setup ang Database at Models para sa **Dynamic Package System**. Dahil Approach B ang napili, naka-link na ang packages sa Hotels, Activities, at Destinations.

## Proposed Database Schema

Gagawa tayo ng main table para sa Packages, at dalawang *Pivot Tables* para ma-connect natin sila sa existing Hotels at Activities nang dynamic.

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
- `is_active` (Boolean) - Pwedeng i-turn off ng admin pag tapos na ang promo.

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

## Bakit Maganda Ang Setup Na Ito?
1. **Dynamic Display sa Frontend**: Sa UI mo, pwede mong i-loop yung `$package->generic_inclusions` para lumabas yung mga checkmarks. Tapos pwede mong i-loop yung `$package->activities` para makita ng user yung pictures ng mismong island hopping na kasama sa package.
2. **Flexible sa Admin**: Sa Admin Panel, may form kung saan ttype lang nila yung details, tapos may multi-select dropdown para sa Hotels at Activities na isasama nila sa package.

## Next Steps para bukas:
1. I-ge-generate natin ang mga Migration files para sa tatlong tables na ito.
2. I-se-setup natin ang `Package` model at ang mga Relationships (`belongsTo`, `belongsToMany`).
3. Gagawa tayo ng Seeder para magkaroon ka agad ng dummy data na kamukha nung nasa poster para ma-test mo agad sa UI!
