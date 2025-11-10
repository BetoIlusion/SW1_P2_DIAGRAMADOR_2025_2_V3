<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class CollaboratorUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $diagramaId;
    public $userId;
    public $action;

    public function __construct($diagramaId, $userId, $action)
    {
        $this->diagramaId = $diagramaId;
        $this->userId = $userId;
        $this->action = $action;

        Log::info('EVENT_CONSTRUCTED', [
            'event' => 'CollaboratorUpdated',
            'diagrama_id' => $diagramaId,
            'user_id' => $userId,
            'action' => $action,
            'channels' => ['collaborations']
        ]);
    }

    public function broadcastOn()
    {
        $channels = [new Channel('collaborations')];

        Log::info('BROADCAST_ON', [
            'channels' => array_map(fn($c) => $c->name, $channels)
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        Log::info('BROADCAST_AS', ['name' => 'collaborator.updated']);
        return 'collaborator.updated';
    }

    public function broadcastWith()
    {
        return [
            'diagrama_id' => $this->diagramaId,
            'user_id' => $this->userId,
            'action' => $this->action,
        ];
    }
}
