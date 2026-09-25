<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Component;

class CartTable extends Component
{
    public array $qty = [];

    public function mount()
    {
        foreach (CartService::detailed() as $item) {
            $this->qty[$item->key] = $item->qty;
        }
    }

    public function updatedQty($value, $key)
    {
        CartService::update((string) $key, (int) $value);
        $this->qty[$key] = CartService::raw()[$key]['qty'] ?? 0;
        $this->dispatch('cart-updated');
    }

    public function remove($key)
    {
        CartService::remove((string) $key);
        unset($this->qty[$key]);
        $this->dispatch('cart-updated');
    }

    public function getTotalsProperty()
    {
        return CartService::totals();
    }

    public function render()
    {
        return view('livewire.cart-table', [
            'totals' => $this->totals,
        ]);
    }
}
