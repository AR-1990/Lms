<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Everything chat sends live: message.sent, message.updated (edit / delete / reaction), messages.read and
 * conversation.updated (chat list and unread counters). Queued and rescued like TaskChanged.
 */
class ChatEvent implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, string>  $channels  private channel names
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $channels, public string $name, public array $payload) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $channel): PrivateChannel => new PrivateChannel($channel), array_values(array_unique($this->channels)));
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
