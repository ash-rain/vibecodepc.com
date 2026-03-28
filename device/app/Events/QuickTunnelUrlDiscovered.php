<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\QuickTunnel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickTunnelUrlDiscovered implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public QuickTunnel $tunnel,
        public string $url,
    ) {}

    public function broadcastOn(): array
    {
        return [
            'quick-tunnels',
        ];
    }

    public function broadcastAs(): string
    {
        return 'tunnel.url.discovered';
    }
}
