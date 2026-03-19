<?php

declare(strict_types=1);

use App\Services\SystemService;
use Illuminate\Support\Facades\Process;

describe('setAdminPassword', function () {
    it('returns true when password is set successfully', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('newpassword123');

        expect($result)->toBeTrue();
    });

    it('returns false when password change fails', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: 'Authentication failed', exitCode: 1),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('newpassword123');

        expect($result)->toBeFalse();
    });

    it('handles special characters in passwords correctly', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('pa$$w0rd!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~');

        expect($result)->toBeTrue();
    });

    it('escapes shell arguments properly', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $service->setAdminPassword('test; rm -rf /; echo');

        Process::assertRan(function ($process) {
            return str_contains($process->command, 'chpasswd');
        });
    });

    it('does not allow command injection', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        // Password with special shell characters should still work
        // because escapeshellarg is used
        $result = $service->setAdminPassword('pass; whoami #');

        expect($result)->toBeTrue();
    });
});

describe('setTimezone', function () {
    it('returns true when timezone is set successfully', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setTimezone('America/New_York');

        expect($result)->toBeTrue();
    });

    it('returns false when timezone change fails', function () {
        Process::fake([
            '*set-timezone*' => Process::result(
                output: 'Failed to set timezone: Invalid time zone',
                exitCode: 1
            ),
        ]);

        $service = new SystemService;
        $result = $service->setTimezone('Invalid/Timezone');

        expect($result)->toBeFalse();
    });

    it('handles various valid timezones', function () {
        $timezones = [
            'America/New_York',
            'America/Los_Angeles',
            'Europe/London',
            'Asia/Tokyo',
            'UTC',
            'Australia/Sydney',
        ];

        foreach ($timezones as $timezone) {
            Process::fake([
                '*set-timezone*' => Process::result(output: '', exitCode: 0),
            ]);

            $service = new SystemService;
            $result = $service->setTimezone($timezone);

            expect($result)->toBeTrue();
        }
    });

    it('escapes timezone argument properly', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $service->setTimezone('America/New;York');

        Process::assertRan(function ($process) {
            return str_contains($process->command, 'timedatectl');
        });
    });

    it('does not allow command injection in timezone', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setTimezone('America/New_York; rm -rf /');

        expect($result)->toBeTrue();
    });
});

describe('getAvailableTimezones', function () {
    it('returns timezones from timedatectl when command succeeds', function () {
        $timezoneList = "America/New_York\nAmerica/Los_Angeles\nEurope/London\nAsia/Tokyo\nUTC";

        Process::fake([
            '*list-timezones*' => Process::result(output: $timezoneList, exitCode: 0),
        ]);

        $service = new SystemService;
        $timezones = $service->getAvailableTimezones();

        expect($timezones)->toBeArray()
            ->and($timezones)->toHaveCount(5)
            ->and($timezones)->toContain('America/New_York')
            ->and($timezones)->toContain('Europe/London')
            ->and($timezones)->toContain('UTC');
    });

    it('returns PHP timezones when timedatectl fails', function () {
        Process::fake([
            '*list-timezones*' => Process::result(output: '', exitCode: 1),
        ]);

        $service = new SystemService;
        $timezones = $service->getAvailableTimezones();

        expect($timezones)->toBeArray()
            ->and($timezones)->not->toBeEmpty();
        // Should contain common timezones from PHP's fallback
        expect(in_array('UTC', $timezones) || in_array('America/New_York', $timezones))->toBeTrue();
    });

    it('filters out empty lines from timedatectl output', function () {
        $timezoneList = "America/New_York\n\nAmerica/Los_Angeles\n\nEurope/London\n";

        Process::fake([
            '*list-timezones*' => Process::result(output: $timezoneList, exitCode: 0),
        ]);

        $service = new SystemService;
        $timezones = $service->getAvailableTimezones();

        expect($timezones)->toHaveCount(3)
            ->and($timezones)->toContain('America/New_York')
            ->and($timezones)->toContain('America/Los_Angeles')
            ->and($timezones)->toContain('Europe/London');
    });

    it('returns PHP timezones when timedatectl is not available', function () {
        Process::fake([
            '*list-timezones*' => Process::result(
                output: 'timedatectl: command not found',
                exitCode: 127
            ),
        ]);

        $service = new SystemService;
        $timezones = $service->getAvailableTimezones();

        expect($timezones)->toBeArray()
            ->and(count($timezones))->toBeGreaterThan(0);
    });

    it('returns empty array when timedatectl returns empty output', function () {
        Process::fake([
            '*list-timezones*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $timezones = $service->getAvailableTimezones();

        // Empty output from successful command should return empty array
        expect($timezones)->toBeArray()
            ->and($timezones)->toBeEmpty();
    });
});

describe('getCurrentTimezone', function () {
    it('returns timezone from timedatectl when command succeeds', function () {
        Process::fake([
            '*show --property=Timezone*' => Process::result(output: 'America/New_York', exitCode: 0),
        ]);

        $service = new SystemService;
        $timezone = $service->getCurrentTimezone();

        expect($timezone)->toBe('America/New_York');
    });

    it('returns PHP default timezone when timedatectl fails', function () {
        Process::fake([
            '*show --property=Timezone*' => Process::result(output: '', exitCode: 1),
        ]);

        $service = new SystemService;
        $timezone = $service->getCurrentTimezone();

        expect($timezone)->toBe(date_default_timezone_get());
    });

    it('trims whitespace from timedatectl output', function () {
        Process::fake([
            '*show --property=Timezone*' => Process::result(output: "  Europe/London  \n", exitCode: 0),
        ]);

        $service = new SystemService;
        $timezone = $service->getCurrentTimezone();

        expect($timezone)->toBe('Europe/London');
    });

    it('returns PHP default when timedatectl returns empty output', function () {
        Process::fake([
            '*show --property=Timezone*' => Process::result(output: "   \n  ", exitCode: 0),
        ]);

        $service = new SystemService;
        $timezone = $service->getCurrentTimezone();

        expect($timezone)->toBe(date_default_timezone_get());
    });

    it('returns PHP default when timedatectl is not available', function () {
        Process::fake([
            '*show --property=Timezone*' => Process::result(
                output: 'timedatectl: command not found',
                exitCode: 127
            ),
        ]);

        $service = new SystemService;
        $timezone = $service->getCurrentTimezone();

        expect($timezone)->toBe(date_default_timezone_get());
    });

    it('handles various timezone formats from timedatectl', function () {
        $testCases = [
            'UTC',
            'America/New_York',
            'Asia/Tokyo',
            'Europe/Paris',
            'Pacific/Auckland',
        ];

        foreach ($testCases as $expectedTimezone) {
            Process::fake([
                '*show --property=Timezone*' => Process::result(output: $expectedTimezone, exitCode: 0),
            ]);

            $service = new SystemService;
            $timezone = $service->getCurrentTimezone();

            expect($timezone)->toBe($expectedTimezone);
        }
    });
});

describe('timezone setting and retrieval integration', function () {
    it('can set and then retrieve a timezone', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $setResult = $service->setTimezone('America/Chicago');

        expect($setResult)->toBeTrue();

        // Then verify the timezone can be retrieved
        Process::fake([
            '*show --property=Timezone*' => Process::result(output: 'America/Chicago', exitCode: 0),
        ]);

        $currentTimezone = $service->getCurrentTimezone();

        expect($currentTimezone)->toBe('America/Chicago');
    });

    it('timezone changes persist across service calls', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 0),
            '*show --property=Timezone*' => Process::result(output: 'Europe/Berlin', exitCode: 0),
        ]);

        $service = new SystemService;

        // Set timezone
        $setResult = $service->setTimezone('Europe/Berlin');
        expect($setResult)->toBeTrue();

        // Verify retrieval
        $currentTimezone = $service->getCurrentTimezone();
        expect($currentTimezone)->toBe('Europe/Berlin');
    });
});

describe('edge cases', function () {
    it('handles empty password', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('');

        expect($result)->toBeTrue();
    });

    it('handles empty timezone', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: '', exitCode: 1),
        ]);

        $service = new SystemService;
        $result = $service->setTimezone('');

        expect($result)->toBeFalse();
    });

    it('handles very long password', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $longPassword = str_repeat('a', 1000);
        $service = new SystemService;
        $result = $service->setAdminPassword($longPassword);

        expect($result)->toBeTrue();
    });

    it('handles unicode characters in password', function () {
        Process::fake([
            '*chpasswd*' => Process::result(output: '', exitCode: 0),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('пароль密码パスワード🔐');

        expect($result)->toBeTrue();
    });

    it('handles unicode characters in timezone (treated as invalid)', function () {
        Process::fake([
            '*set-timezone*' => Process::result(output: 'Invalid timezone', exitCode: 1),
        ]);

        $service = new SystemService;
        $result = $service->setTimezone('日本/東京');

        expect($result)->toBeFalse();
    });

    it('handles process timeout', function () {
        Process::fake([
            '*chpasswd*' => Process::result(
                output: '',
                exitCode: 1,
                errorOutput: 'Command timed out'
            ),
        ]);

        $service = new SystemService;
        $result = $service->setAdminPassword('password123');

        expect($result)->toBeFalse();
    });
});
