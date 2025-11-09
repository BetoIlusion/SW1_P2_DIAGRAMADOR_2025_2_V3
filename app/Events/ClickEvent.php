<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClickEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message; // Optional data to send

    public function __construct($message = 'Button clicked!')
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('click-channel'), // Public channel for testing
        ];
    }

    public function broadcastAs()
    {
        return 'click-event';
    }
}
