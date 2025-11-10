<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiagramaActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $diagramaId;
    public $updatedDiagramJson;

    public function __construct($diagramaId, $updatedDiagramJson)
    {
        $this->diagramaId = $diagramaId;
        $this->updatedDiagramJson = $updatedDiagramJson;

        Log::info('DIAGRAMA_ACTUALIZADO_CONSTRUCTED', [
            'diagrama_id' => $diagramaId,
            'channels' => ['diagrama.' . $diagramaId]
        ]);
    }

    public function broadcastOn()
    {
        $channels = [new Channel('diagrama.' . $this->diagramaId)];

        Log::info('DIAGRAMA_BROADCAST_ON', [
            'channels' => array_map(fn($c) => $c->name, $channels)
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        Log::info('DIAGRAMA_BROADCAST_AS', ['name' => 'diagrama.updated']);
        return 'diagrama.updated';
    }

    public function broadcastWith()
    {
        return [
            'diagrama_id' => $this->diagramaId,
            'updated_diagram' => $this->updatedDiagramJson,
        ];
    }
}