<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Component;
use Livewire\Attributes\On;

class HeaderCart extends Component
{
    public int $count = 0;

    public function mount()
    {
        $this->count = CartService::count();
    }

    #[On('cart-updated')]
    public function refreshCount()
    {
        $this->count = CartService::count();
    }

    public function render()
    {
        return view('livewire.header-cart');
    }
}
