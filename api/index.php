<?php

declare(strict_types=1);

use repositories\WorkTimeRepository;
use services\WorkTimeService;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repositories/WorkTimeRepository.php';
require_once __DIR__ . '/services/WorkTimeService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response(405, null, ['Only GET is supported.']);
}

$route = trim((string)($_GET['route'] ?? ''));
$repo = new WorkTimeRepository(db());
$service = new WorkTimeService($repo);

try {
    if ($route === 'daily-workers') {
        $date = trim((string)($_GET['date'] ?? ''));
        if (!isValidDate($date)) {
            response(422, null, ['Parameter "date" must be in format YYYY-MM-DD.']);
        }

        $data = $service->getDailyWorkTime($date);
        response(200, $data, []);
    } else if ($route === 'worker-range') {
        $workerIdRaw = trim((string)($_GET['worker_id'] ?? ''));
        $from = trim((string)($_GET['from'] ?? ''));
        $to = trim((string)($_GET['to'] ?? ''));
        $fromValid = isValidDate($from);
        $toValid = isValidDate($to);
        $errors = [];

        if ($workerIdRaw === '' || !ctype_digit($workerIdRaw)) {
            $errors[] = 'Parameter "worker_id" is required and must be numeric.';
        }
        if (!$fromValid) {
            $errors[] = 'Parameter "from" must be in format YYYY-MM-DD.';
        }
        if (!$toValid) {
            $errors[] = 'Parameter "to" must be in format YYYY-MM-DD.';
        }
        if ($fromValid && $toValid && $from > $to) {
            $errors[] = 'Parameter "from" must be less than or equal to "to".';
        }
        if ($errors !== []) {
            response(422, null, $errors);
        }

        $data = $service->getWorkerWorkTimeByDateRange((int)$workerIdRaw, $from, $to);
        response(200, $data, []);
    } else if ($route === 'available-dates') {
        $data = $service->availableDates();
        response(200, $data, []);
    } else {
        response(404, null, ['Unknown route.']);
    }
} catch (Throwable $e) {
    response(500, null, [$e->getMessage()]);
}

function isValidDate(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function response(int $status, mixed $data, array $errors): never
{
    http_response_code($status);
    echo json_encode(
        [
            'success' => $status < 400,
            'data' => $data,
            'errors' => $errors,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}
