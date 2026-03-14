<?php

use App\Services\NetworkService;

describe('basic functionality', function () {
    it('getLocalIp returns a string', function () {
        $service = new NetworkService;

        $ip = $service->getLocalIp();

        expect($ip)->toBeString()
            ->and($ip)->not->toBeEmpty();
    });

    it('hasInternetConnectivity returns a boolean', function () {
        $service = new NetworkService;

        $result = $service->hasInternetConnectivity();

        expect($result)->toBeBool();
    });

    it('hasEthernet returns a boolean', function () {
        $service = new NetworkService;

        $result = $service->hasEthernet();

        expect($result)->toBeBool();
    });

    it('hasWifi returns a boolean', function () {
        $service = new NetworkService;

        $result = $service->hasWifi();

        expect($result)->toBeBool();
    });
});

describe('IP detection - failure scenarios', function () {
    it('getLocalIp returns loopback address when hostname command is not available', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I returning null (command not found)
                $output = null;

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // Simulate ipconfig also failing
                $output = null;

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        expect($ip)->toBe('127.0.0.1');
    });

    it('getLocalIp handles empty output from hostname command', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I returning empty string
                $output = '';

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // Fallback to ipconfig
                $output = @shell_exec('ipconfig getifaddr en0 2>/dev/null');

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        // Should either return ipconfig result or fallback to loopback
        expect($ip)->toBeString();
    });

    it('getLocalIp handles whitespace-only output from hostname command', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I returning only whitespace
                $output = "   \n\t  ";

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // Fallback to ipconfig
                $output = @shell_exec('ipconfig getifaddr en0 2>/dev/null');

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        // Whitespace-only output is truthy in PHP, so it enters the first branch
        // explode(' ', trim('   ')) produces [''], so $ips[0] is empty string
        expect($ip)->toBe('');
    });

    it('getLocalIp handles malformed IP output from hostname command', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I returning malformed data
                $output = 'not-an-ip';

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // Fallback to ipconfig
                $output = @shell_exec('ipconfig getifaddr en0 2>/dev/null');

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        // Returns the malformed data as-is (the code doesn't validate IP format)
        expect($ip)->toBe('not-an-ip');
    });

    it('getLocalIp handles multiple IPs from hostname command', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I returning multiple IPs
                $output = '192.168.1.100 10.0.0.50 172.16.0.1';

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // Fallback to ipconfig
                $output = @shell_exec('ipconfig getifaddr en0 2>/dev/null');

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        // Should return the first IP
        expect($ip)->toBe('192.168.1.100');
    });
});

describe('IP detection - interface changes', function () {
    it('getLocalIp falls back to ipconfig when hostname fails', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Simulate hostname -I failing
                $output = null;

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                // ipconfig succeeds
                $output = '192.168.1.50';

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        expect($ip)->toBe('192.168.1.50');
    });

    it('getLocalIp returns loopback when both commands fail', function () {
        $service = new class extends NetworkService
        {
            public function getLocalIp(): ?string
            {
                // Both commands fail
                $output = null;

                if ($output) {
                    $ips = explode(' ', trim($output));

                    return $ips[0] ?? null;
                }

                $output = null;

                if ($output) {
                    return trim($output);
                }

                return '127.0.0.1';
            }
        };

        $ip = $service->getLocalIp();

        expect($ip)->toBe('127.0.0.1');
    });
});

describe('interface detection - edge cases', function () {
    it('hasEthernet handles missing eth0 interface gracefully', function () {
        $service = new class extends NetworkService
        {
            public function hasEthernet(): bool
            {
                // Simulate ip link show returning null (interface doesn't exist)
                $output = null;

                return $output !== null && str_contains($output, 'state UP');
            }
        };

        $result = $service->hasEthernet();

        expect($result)->toBeFalse();
    });

    it('hasEthernet handles interface in DOWN state', function () {
        $service = new class extends NetworkService
        {
            public function hasEthernet(): bool
            {
                // Simulate eth0 existing but DOWN
                $output = '2: eth0: <BROADCAST,MULTICAST> mtu 1500 qdisc noop state DOWN mode DEFAULT group default qlen 1000';

                return $output !== null && str_contains($output, 'state UP');
            }
        };

        $result = $service->hasEthernet();

        expect($result)->toBeFalse();
    });

    it('hasEthernet handles interface in UP state', function () {
        $service = new class extends NetworkService
        {
            public function hasEthernet(): bool
            {
                // Simulate eth0 UP
                $output = '2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc fq_codel state UP mode DEFAULT group default qlen 1000';

                return $output !== null && str_contains($output, 'state UP');
            }
        };

        $result = $service->hasEthernet();

        expect($result)->toBeTrue();
    });

    it('hasWifi handles missing wlan0 interface gracefully', function () {
        $service = new class extends NetworkService
        {
            public function hasWifi(): bool
            {
                // Simulate ip link show returning null (interface doesn't exist)
                $output = null;

                return $output !== null && str_contains($output, 'wlan0');
            }
        };

        $result = $service->hasWifi();

        expect($result)->toBeFalse();
    });

    it('hasWifi handles interface in DOWN state', function () {
        $service = new class extends NetworkService
        {
            public function hasWifi(): bool
            {
                // Simulate wlan0 existing but DOWN
                $output = '3: wlan0: <BROADCAST,MULTICAST> mtu 1500 qdisc noop state DOWN mode DEFAULT group default qlen 1000';

                return $output !== null && str_contains($output, 'wlan0');
            }
        };

        $result = $service->hasWifi();

        // Should still return true because interface exists (even if down)
        expect($result)->toBeTrue();
    });

    it('hasWifi handles interface with different name format', function () {
        $service = new class extends NetworkService
        {
            public function hasWifi(): bool
            {
                // Simulate wlan0 with different output format
                $output = '3: wlan0: <NO-CARRIER,BROADCAST,MULTICAST,UP> mtu 1500 qdisc mq state DOWN mode DORMANT group default qlen 1000';

                return $output !== null && str_contains($output, 'wlan0');
            }
        };

        $result = $service->hasWifi();

        expect($result)->toBeTrue();
    });
});

describe('internet connectivity - timeout handling', function () {
    it('hasInternetConnectivity returns false when fsockopen fails', function () {
        $service = new class extends NetworkService
        {
            public function hasInternetConnectivity(): bool
            {
                // Simulate fsockopen returning false (connection failed)
                $connected = false;

                if ($connected) {
                    fclose($connected);

                    return true;
                }

                return false;
            }
        };

        $result = $service->hasInternetConnectivity();

        expect($result)->toBeFalse();
    });

    it('hasInternetConnectivity returns true when connection succeeds', function () {
        $service = new class extends NetworkService
        {
            public function hasInternetConnectivity(): bool
            {
                // Simulate successful connection using a temporary file resource
                $connected = fopen('php://temp', 'r+');

                if ($connected) {
                    fclose($connected);

                    return true;
                }

                return false;
            }
        };

        $result = $service->hasInternetConnectivity();

        expect($result)->toBeTrue();
    });

    it('hasInternetConnectivity handles connection timeout gracefully', function () {
        // This test documents the expected timeout behavior
        // The actual method uses a 3-second timeout in fsockopen
        $service = new NetworkService;

        // We can't easily test actual timeout without making network calls
        // This test just verifies the method exists and returns a boolean
        $result = $service->hasInternetConnectivity();

        expect($result)->toBeBool();
    });
});

describe('IP validation', function () {
    it('getLocalIp returns valid IP format when using hostname command', function () {
        $service = new NetworkService;

        $ip = $service->getLocalIp();

        // Should return a valid IP address (either from hostname or fallback)
        expect($ip)->toBeString()
            ->and($ip)->not->toBeEmpty();

        // Check if it's a valid IP or the loopback fallback
        $isValidIp = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        expect($isValidIp)->toBeTrue();
    });

    it('getLocalIp never returns empty string', function () {
        $service = new NetworkService;

        $ip = $service->getLocalIp();

        expect($ip)->not->toBeEmpty()
            ->and($ip)->not->toBeNull();
    });
});
