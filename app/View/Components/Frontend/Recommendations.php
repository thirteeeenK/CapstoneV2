<?php

namespace App\View\Components\Frontend;

use Illuminate\View\Component;

class Recommendations extends Component
{
    public $isPersonalized;

    public $aiRecommendations;

    public $defaultRecommendations;

    public $preferredDestId;

    public $recSessionToken;

    public function __construct($isPersonalized = false, $aiRecommendations = [], $defaultRecommendations = [], $preferredDestId = null, $recSessionToken = null)
    {
        $this->isPersonalized = $isPersonalized;
        $this->aiRecommendations = $aiRecommendations;
        $this->defaultRecommendations = $defaultRecommendations;
        $this->preferredDestId = $preferredDestId;
        $this->recSessionToken = $recSessionToken;
    }

    public function render()
    {
        return view('components.frontend.recommendations');
    }
}
