<?php

namespace App\View\Components\Frontend;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use Illuminate\View\Component;

class Experiences extends Component
{
    use ResolvesImages;

    public $activities;
    public $destinations;

    public function __construct()
    {
        $this->activities = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->orderBy('id', 'asc')
            ->get();

        $this->destinations = DestinationModel::whereHas('activities', function ($query) {
            $query->where('is_shown', true);
        })->orderBy('name', 'asc')->get();
    }

    public function resolveActivityImage($images): string
    {
        $firstImg = is_array($images) && count($images) > 0 ? $images[0] : null;
        return static::resolveImg(
            $firstImg,
            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'
        );
    }

    public function render()
    {
        return view('components.frontend.experiences');
    }
}
