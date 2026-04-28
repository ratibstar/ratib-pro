<?php
declare(strict_types=1);

use App\Controllers\Http\TrackingController;
use App\Controllers\Http\WorkerController;
use App\Controllers\Http\WorkflowController;
use App\Core\Container;

/** @return array<string, callable(array<string, mixed>):array> */
return static function (Container $container): array {
    return [
        'POST /workers' => fn (array $payload) => $container->get(WorkerController::class)->store($payload),
        'POST /tracking/move' => fn (array $payload) => $container->get(TrackingController::class)->move($payload),
    ];
};
