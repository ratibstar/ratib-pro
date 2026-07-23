<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Configurable reminder schedule and type mapping.
 *
 * Defaults load from config/notification-policy.php — never rely on inlined
 * day lists in Engine logic. Pass $config to customize per tenant/future admin.
 *
 * Does not send, schedule, or render anything.
 */
final class NotificationPolicy
{
    /** @var list<int> */
    private array $triggerDays;

    /** @var array<int, string> */
    private array $typeByTriggerDay;

    /** @var list<string> */
    private array $channels;

    /**
     * @param array{
     *   trigger_days?: list<int>,
     *   type_by_trigger_day?: array<int|string, string>,
     *   channels?: list<string>
     * }|null $config
     */
    public function __construct(?array $config = null)
    {
        $loaded = $config ?? self::loadDefaultConfig();
        $days = $loaded['trigger_days'] ?? [];
        if (!is_array($days) || $days === []) {
            $days = self::loadDefaultConfig()['trigger_days'];
        }
        $normalizedDays = [];
        foreach ($days as $day) {
            $normalizedDays[] = (int) $day;
        }
        sort($normalizedDays);
        $this->triggerDays = array_values(array_unique($normalizedDays));

        $map = $loaded['type_by_trigger_day'] ?? [];
        $this->typeByTriggerDay = [];
        if (is_array($map)) {
            foreach ($map as $day => $type) {
                $this->typeByTriggerDay[(int) $day] = strtoupper(trim((string) $type));
            }
        }

        $channels = $loaded['channels'] ?? NotificationChannel::all();
        $this->channels = [];
        if (is_array($channels)) {
            foreach ($channels as $ch) {
                $this->channels[] = (string) $ch;
            }
        }
    }

    /**
     * @return array{
     *   trigger_days: list<int>,
     *   type_by_trigger_day: array<int, string>,
     *   channels: list<string>
     * }
     */
    public static function loadDefaultConfig(): array
    {
        $path = SubscriptionModule::rootPath() . '/config/notification-policy.php';
        if (is_file($path)) {
            $cfg = require $path;
            if (is_array($cfg)) {
                /** @var array{trigger_days?: list<int>, type_by_trigger_day?: array<int, string>, channels?: list<string>} $cfg */
                return $cfg;
            }
        }

        return [
            'trigger_days' => [14, 11, 8, 5, 3, 2, 1, 0, -1, -2, -3, -4, -5, -6, -7],
            'type_by_trigger_day' => [
                14 => NotificationType::REMINDER,
                11 => NotificationType::REMINDER,
                8 => NotificationType::REMINDER,
                5 => NotificationType::REMINDER,
                3 => NotificationType::REMINDER,
                2 => NotificationType::REMINDER,
                1 => NotificationType::REMINDER,
                0 => NotificationType::FINAL_WARNING,
                -1 => NotificationType::GRACE,
                -2 => NotificationType::GRACE,
                -3 => NotificationType::GRACE,
                -4 => NotificationType::GRACE,
                -5 => NotificationType::GRACE,
                -6 => NotificationType::GRACE,
                -7 => NotificationType::GRACE,
            ],
            'channels' => NotificationChannel::all(),
        ];
    }

    /** @return list<int> */
    public function triggerDays(): array
    {
        return $this->triggerDays;
    }

    public function isTriggerDay(int $daysRemaining): bool
    {
        return in_array($daysRemaining, $this->triggerDays, true);
    }

    public function typeForTriggerDay(int $triggerDay): string
    {
        if (isset($this->typeByTriggerDay[$triggerDay])
            && NotificationType::isKnown($this->typeByTriggerDay[$triggerDay])) {
            return $this->typeByTriggerDay[$triggerDay];
        }

        if ($triggerDay > 0) {
            return NotificationType::REMINDER;
        }
        if ($triggerDay === 0) {
            return NotificationType::FINAL_WARNING;
        }

        return NotificationType::GRACE;
    }

    /**
     * Next trigger day strictly less than current daysRemaining, or null.
     * (Walking toward and past expiration.)
     */
    public function nextTriggerDayAfter(int $daysRemaining): ?int
    {
        $candidates = [];
        foreach ($this->triggerDays as $day) {
            if ($day < $daysRemaining) {
                $candidates[] = $day;
            }
        }
        if ($candidates === []) {
            return null;
        }
        return max($candidates);
    }

    /** @return list<string> */
    public function channels(): array
    {
        return $this->channels;
    }
}
