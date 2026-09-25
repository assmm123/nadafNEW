<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        $message = (new MailMessage)
            ->subject(__('mail.customer_subject', ['code' => $order->order_code]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.thanks_line', ['code' => $order->order_code]))
            ->line(__('mail.total_line', [
                'usd' => fmt_usd($order->total_usd),
                'syp' => fmt_syp($order->total_syp),
            ]))
            ->line(__('mail.keep_code'))
            ->action(__('mail.view_order'), route('account.order', $order->order_code));

        $message->line('---');
        foreach ($order->items as $item) {
            $message->line($item->displayName().' × '.$item->quantity.' = '.fmt_usd($item->total_price_usd));
        }

        return $message;
    }
}
