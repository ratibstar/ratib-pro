<?php
declare(strict_types=1);

/**
 * Default subscription notification policy (customizable).
 *
 * trigger_days: signed offsets vs subscription_end
 *   positive = days before end
 *   0        = end day
 *   negative = days after end (+1 ⇒ -1, …)
 *
 * type_by_trigger_day: maps each trigger day to NotificationType
 * channels: future delivery targets (unused for sending in Phase 3)
 *
 * Override by passing a custom array into NotificationPolicy, or replace this file.
 *
 * @return array{
 *   trigger_days: list<int>,
 *   type_by_trigger_day: array<int, string>,
 *   channels: list<string>
 * }
 */
return [
    'trigger_days' => [14, 11, 8, 5, 3, 2, 1, 0, -1, -2, -3, -4, -5, -6, -7],
    'type_by_trigger_day' => [
        14 => 'REMINDER',
        11 => 'REMINDER',
        8 => 'REMINDER',
        5 => 'REMINDER',
        3 => 'REMINDER',
        2 => 'REMINDER',
        1 => 'REMINDER',
        0 => 'FINAL_WARNING',
        -1 => 'GRACE',
        -2 => 'GRACE',
        -3 => 'GRACE',
        -4 => 'GRACE',
        -5 => 'GRACE',
        -6 => 'GRACE',
        -7 => 'GRACE',
    ],
    'channels' => ['email', 'push', 'in_app', 'whatsapp', 'sms'],
];
