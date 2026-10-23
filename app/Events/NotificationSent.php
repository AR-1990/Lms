<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new bell notification, pushed live to each recipient's App.Models.User.{id} channel so the bell
 * updates and plays its sound at once. Queued and rescued like ChatEvent.
 */
class NotificationSent implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $userIds, public array $payload) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $userId): PrivateChannel => new PrivateChannel('App.Models.User.'.$userId), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
