<?php

namespace App\View\Components\Frontend;

use App\Concerns\ResolvesImages;
use App\Models\DestinationModel;
use Illuminate\View\Component;

class Destinations extends Component
{
    use ResolvesImages;

    public $destinations;

    public function __construct()
    {
        $this->destinations = DestinationModel::orderBy('id', 'asc')->get();
    }

    public function resolveDestinationImage($image): string
    {
        return static::resolveImg(
            $image,
            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'
        );
    }

    public function render()
    {
        return view('components.frontend.destinations');
    }
}
