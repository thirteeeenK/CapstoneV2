<?php

namespace Database\Seeders;

use App\Models\DestinationModel;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

/**
 * General destination guidance for the chatbot's knowledge base: how to get
 * there, getting around, where to eat, what to see, when to go and what to
 * bring.
 *
 * Each article covers one topic and stays under the 600 characters the
 * chatbot quotes, so it is retrieved and shown whole. The text is general
 * travel knowledge written for this project, not an official source: it
 * avoids prices, schedules and fee amounts because those change, and it
 * should be reviewed by the travel desk before it is relied on in production.
 * Safe to run again; articles are matched by destination and title.
 */
class KnowledgeArticleSeeder extends Seeder
{
    private const SOURCE = 'SunnyTrips destination notes (general guidance)';

    public function run(): void
    {
        foreach ($this->articles() as $destinationName => $articles) {
            $destination = DestinationModel::where('name', $destinationName)->first();
            if (! $destination) {
                $this->command?->warn("Skipped knowledge articles for {$destinationName}: destination not found.");

                continue;
            }
            foreach ($articles as [$category, $title, $body]) {
                KnowledgeArticle::updateOrCreate(
                    ['destination_id' => $destination->id, 'title' => $title],
                    [
                        'body' => $body,
                        'category' => $category,
                        'source_label' => self::SOURCE,
                        'source_url' => null,
                        'reviewed_at' => now()->toDateString(),
                        'expires_at' => now()->addYear()->toDateString(),
                        'status' => 'published',
                        'reviewed_by' => 'Seeded general guidance',
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, list<array{0: string, 1: string, 2: string}>> destination name => [category, title, body]
     */
    private function articles(): array
    {
        return [
            'Boracay' => [
                ['destination_access', 'How to get to Boracay from Manila',
                    'Boracay has no airport of its own. The fastest way from Manila is to fly to Caticlan (Godofredo P. Ramos Airport), about an hour away, then take a short land transfer to Caticlan Jetty Port and a boat of around 15 minutes to the island. You can also fly to Kalibo International Airport and travel by road to Caticlan, which takes roughly two hours. Boats land at Cagban or Tambisaan port depending on the season, and an e-trike takes you from the port to your hotel. Be ready to show a confirmed hotel booking at the jetty port.'],
                ['local_transport', 'Getting around Boracay',
                    'Boracay is small and easy to get around. Electric tricycles (e-trikes) run along the main road and are the usual transport between Station 1, Station 2, Station 3 and other beaches; you can share a ride on a regular route or hire one privately. Along White Beach most people simply walk, since the beachfront path connects the three stations and is closed to vehicles. Hotels away from White Beach often run their own shuttle. Agree on the fare before a private trip.'],
                ['dining', 'Where to eat in Boracay',
                    'Most restaurants in Boracay are along White Beach and in D\'Mall, the open-air shopping and dining area in Station 2, where you will find Filipino food, seafood, grills, cafes and international restaurants. Station 1 is quieter with more upscale dining, while Station 3 has more budget eateries. At the D\'Talipapa seafood market you can buy fresh seafood and have a nearby kitchen cook it for you. Eating, drinking and smoking on the sand of White Beach itself are not allowed, so dine at the restaurants facing the beach.'],
                ['attractions', 'Best beaches and spots to see in Boracay',
                    'White Beach is the main attraction: about four kilometres of fine white sand divided into Station 1, 2 and 3, and the best place for sunset. Puka Shell Beach in the north is quieter with coarser sand. Diniwid Beach is a small cove a short walk from Station 1. Bulabog Beach on the east side is the spot for kitesurfing and windsurfing. Other well-known spots are Willy\'s Rock off Station 1 and the Mount Luho viewpoint, the highest point on the island.'],
                ['practical_guidance', 'Best time to visit Boracay',
                    'The best time to visit Boracay is the dry season, roughly November to May, when the sea on the White Beach side is calm and clear. December to April is the peak period, and Christmas, New Year and Holy Week are the busiest and most crowded times. June to October is the rainy season: showers and rough seas are more common and boat trips can be cancelled, but the island is quieter. Typhoons are possible from about July to November, so check the forecast before you travel.'],
                ['practical_guidance', 'What to bring and pack for Boracay',
                    'Pack light beachwear, a hat, sunglasses and reef-safe sunscreen, since the sun is strong all year. Bring a dry bag or waterproof phone case for boat trips, and water shoes if you plan to visit rocky coves or go island hopping. A light rain jacket helps from June to October. Bring enough cash for small purchases and tricycle rides; ATMs are available around D\'Mall but can run out during busy weeks. Keep a valid ID and your hotel booking confirmation with you for the port.'],
                ['practical_guidance', 'Fees to expect when entering Boracay',
                    'Visitors to Boracay pay three charges at Caticlan Jetty Port before boarding: a terminal fee, an environmental fee and the boat fare. They are usually paid at separate counters, so allow extra time at the port. The amounts change from time to time, so confirm the current rates at the port or with your hotel. Some resorts and transfer services include these fees in their arrival package; ask when you book your transfer.'],
                ['activity_requirements', 'Requirements for water activities in Boracay',
                    'Most water activities in Boracay, such as island hopping, parasailing, banana boat rides and helmet diving, need no experience, and operators provide life vests and a safety briefing. You do not need to know how to swim for many of them, but tell the crew if you cannot. Introductory scuba dives are open to beginners with an instructor, while regular fun dives need a diving certification. Operators may set age, weight or health limits, and activities are cancelled when the sea is rough.'],
                ['accessibility', 'Accessibility in Boracay',
                    'Boracay can be visited with limited mobility, but it takes planning. Getting there involves boarding a small boat at the jetty port, where staff can assist with a wheelchair. The White Beach path is flat but sandy in places, and many smaller hotels have steps and no lift. Ask your hotel about ground-floor or accessible rooms, ramps and help with port transfers before you book. Water activities have their own limits; check with the operator.'],
            ],
            'El Nido' => [
                ['destination_access', 'How to get to El Nido from Manila',
                    'There are two usual ways to reach El Nido from Manila. The fastest is a direct flight to El Nido\'s Lio Airport, which takes about an hour and a half; the airport is a short ride from town. The more common and cheaper way is to fly to Puerto Princesa, then travel north by van or bus, a road trip of around five to six hours. Shared vans leave through the day and can be booked ahead. El Nido is also linked to Coron by ferry, which takes several hours and depends on sea conditions.'],
                ['local_transport', 'Getting around El Nido',
                    'El Nido town is compact and you can walk between most hotels, restaurants and the beach where island hopping boats leave. Tricycles are the main transport for short trips, such as to Corong-Corong, Las Cabanas Beach and Lio. For Nacpan Beach and other spots further north, take a shuttle van, hire a tricycle for the day or rent a motorbike. Agree on the tricycle fare before you ride, and expect some rough or unpaved roads outside town.'],
                ['dining', 'Where to eat in El Nido',
                    'Most places to eat are in El Nido town proper, along the beachfront and the small streets behind it, with Filipino food, grilled seafood, pizza, cafes and bars. Corong-Corong and Las Cabanas Beach have restaurants and bars that are popular at sunset, and the Lio Beach area has a quieter line of restaurants. Island hopping tours normally include a grilled lunch served on a beach. Restaurants in town fill up in the evening during peak season, so go early or expect a wait.'],
                ['attractions', 'Best beaches, lagoons and spots to see in El Nido',
                    'El Nido is best known for island hopping in Bacuit Bay, sold as Tours A, B, C and D. The highlights are the Big Lagoon and Small Lagoon at Miniloc Island, Secret Lagoon, Shimizu Island, Seven Commandos Beach, Hidden Beach, Matinloc Shrine and Snake Island. On the mainland, Nacpan Beach is a long, wide stretch of sand north of town, Las Cabanas Beach is the favourite spot for sunset, and Taraw Cliff has a viewpoint over the town and bay.'],
                ['practical_guidance', 'Best time to visit El Nido',
                    'The best time to visit El Nido is the dry season, roughly December to May, when the sea is calmer and island hopping tours run most reliably. March to May is the hottest and usually has the clearest water. June to November is the rainy season: rain and wind are more common, and the coast guard can cancel boat tours on short notice when the sea is rough. Peak periods such as Christmas, New Year and Holy Week are busy, so book rooms and tours early.'],
                ['practical_guidance', 'What to bring and pack for El Nido',
                    'For El Nido, pack swimwear, a hat, reef-safe sunscreen and a dry bag, because you will be in and out of boats. Water shoes are useful: many lagoon and beach landings are rocky. A waterproof phone case and a quick-dry towel help on tours, and motion-sickness tablets are worth bringing if you get seasick. Bring enough cash for tours, tricycles and small shops, since ATMs are few and not always working. Power cuts and slow internet can happen, so a power bank is useful.'],
                ['practical_guidance', 'Fees to expect in El Nido',
                    'Visitors who join island hopping tours in El Nido pay an eco-tourism development fee, which is valid for several days, so keep the receipt. Some sites, such as the Big Lagoon and Small Lagoon, charge their own entrance fee, and kayak rental there is extra. Tour prices may or may not include these fees. The amounts change from time to time, so confirm what is included with your tour operator or hotel before you pay.'],
                ['activity_requirements', 'Requirements for island hopping and water activities in El Nido',
                    'Island hopping in El Nido needs no experience. Boats carry life vests and the crew will ask you to wear one in the water. Some stops mean wading from the boat or swimming a short distance, and the lagoons are best explored by kayak, so tell the crew if you cannot swim. Scuba diving is available: beginners can do an introductory dive with an instructor, while regular dives need a certification. Tours are cancelled when the coast guard stops boats because of rough seas.'],
                ['accessibility', 'Accessibility in El Nido',
                    'El Nido is harder to visit with limited mobility than a city destination. Streets in town are narrow and uneven, and many hotels have stairs and no lift. Island hopping boats are boarded from the beach, usually by wading and climbing a small ladder, and most lagoon and beach stops have no ramps. Ask your hotel about ground-floor or accessible rooms and about a private boat with crew assistance, and choose beaches reachable by road such as Lio or Nacpan.'],
            ],
        ];
    }
}
