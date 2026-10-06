@php
    // ── Edit these 3 entries later with real names / roles / photos ──
    $devs = [
        ['name' => 'John Kenly Pamor', 'role' => 'Software Developer', 'photo' => asset('images/kalbo.png'), 'fallback' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=400&q=80'],
        ['name' => 'Erica Mae Bonite', 'role' => 'Software Developer', 'photo' => asset('images/kalbo1.png'), 'fallback' => 'https://instagram.fmnl45-1.fna.fbcdn.net/v/t51.82787-15/589107987_17927183145176232_8857162012663560499_n.webp?_nc_cat=104&_nc_map=urlgen_bucketless&ig_cache_key=Mzc4Mzc0MjI1NzYyODE2MzY2OA%3D%3D.3-ccb7-5&ccb=7-5&_nc_sid=58cdad&efg=eyJ2ZW5jb2RlX3RhZyI6IkNBUk9VU0VMX0lURU0ueHBpZHMuMTQ0MC5zZHIucmVndWxhcl9waG90by5DMyJ9&_nc_ohc=KPnzz1p5FkQQ7kNvwE46YOK&_nc_oc=Adq4oSykv9_1JyXPUn2zAYTL_F54geky1hmP8SPRpPOA0JqZfgl8jeHfkwR05K_4ObY&_nc_zt=23&_nc_ht=instagram.fmnl45-1.fna&_nc_gid=PirN0-dTA8tqYxEewJ4iOw&_nc_ss=7baaf&oh=00_AQOMNH74JMI2Ax3HEFexTvxwcZz-HqZ4PZRhmCINSAlofg&oe=6ACA3530'],
        ['name' => 'Bien Batan', 'role' => 'Software Developer', 'photo' => asset('images/kalbo2.png'), 'fallback' => 'https://scontent.fmnl45-2.fna.fbcdn.net/v/t39.30808-1/412651909_1821758368264092_5947203027314665058_n.jpg?stp=dst-jpg_tt6&cstp=mx606x599&ctp=s200x200&_nc_cat=107&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=e99d92&_nc_eui2=AeH-Jlgz8EDwFGcWMTw9-adRPeEESbOgM3w94QRJs6AzfL3kVsxL19T_j4rnn_c9ZFVJIlQL3tBGkNvkIfa_0NjL&_nc_ohc=hO5Xa1fKD7UQ7kNvwGPMjgX&_nc_oc=AdrkC5mv3kqdyNi4giBN08gbxIariTK9USRvJDRdqESKE6kG1xWklVQcvCH2dvxqI78&_nc_zt=24&_nc_ht=scontent.fmnl45-2.fna&_nc_gid=85R23tIug4rVdtqey5gyew&_nc_ss=7b2a8&oh=00_AQP8jf664LR-fl10XkzWxS7y45qqwvwuev1PDQpjl_PAxg&oe=6ACA3CED'],
    ];
@endphp

<x-frontend.layout title="Meet the Developers | SunnyTrips">
    <section class="bg-sand-50 py-16 pt-28 sm:py-20 sm:pt-32">
        <div class="mx-auto max-w-5xl px-6 text-center">
            <p class="font-label text-xs uppercase font-bold tracking-[0.2em] text-primary">Easter egg — shh</p>
            <h1 class="font-headline mt-3 text-3xl font-bold text-ink-900 sm:text-4xl">Meet the Developers</h1>
            <p class="font-body mx-auto mt-3 max-w-xl text-sm text-ink-500 leading-relaxed">
                The humans behind SunnyTrips. Swap these placeholders with real photos later.
            </p>

            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($devs as $dev)
                    <div class="rounded-2xl border border-sand-200 bg-white p-8 shadow-sm">
                        <img src="{{ $dev['photo'] }}" data-fallback="{{ $dev['fallback'] }}"
                            onerror="this.onerror=null;this.src=this.dataset.fallback" alt="{{ $dev['name'] }}"
                            class="mx-auto h-32 w-32 rounded-full object-cover ring-4 ring-ocean-100" />
                        <h2 class="font-headline mt-5 text-lg font-semibold text-ink-900">{{ $dev['name'] }}</h2>
                        <p class="font-body mt-1 text-xs text-ink-500">{{ $dev['role'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-frontend.layout>