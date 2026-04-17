<?php

declare(strict_types=1);

use repositories\WorkTimeRepository;
use services\WorkTimeCalculator;
use services\WorkTimeResponseBuilder;
use services\WorkTimeService;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repositories/WorkTimeRepository.php';
require_once __DIR__ . '/services/WorkTimeCalculator.php';
require_once __DIR__ . '/services/WorkTimeResponseBuilder.php';
require_once __DIR__ . '/services/WorkTimeService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response(405, null, ['Only GET is supported.']);
}

$route = trim((string)($_GET['route'] ?? ''));
$repo = new WorkTimeRepository(db());
$workTimeCalculator = new WorkTimeCalculator();
$responseBuilder = new WorkTimeResponseBuilder();
$service = new WorkTimeService($repo, $workTimeCalculator, $responseBuilder);

try {
    if ($route === 'daily-workers') {
        $date = trim((string)($_GET['date'] ?? ''));

        if (!isValidDate($date)) {
            response(422, null, ['Parameter "date" must be in format YYYY-MM-DD.']);
        }

        $data = $service->getDailyWorkTime($date);
        response(200, $data, []);

    } else if ($route === 'worker-range') {
        $workerId = trim((string)($_GET['worker_id'] ?? ''));
        $from = trim((string)($_GET['from'] ?? ''));
        $to = trim((string)($_GET['to'] ?? ''));

        $errors = [];

        if ($workerId === '' || !ctype_digit($workerId)) {
            $errors[] = 'Parameter "worker_id" is required and must be numeric.';
        }
        if (!isValidDate($from)) {
            $errors[] = 'Parameter "from" must be in format YYYY-MM-DD.';
        }
        if (!isValidDate($to)) {
            $errors[] = 'Parameter "to" must be in format YYYY-MM-DD.';
        }
        if (empty($errors) && $from > $to) {
            $errors[] = 'Parameter "from" must be less than or equal to "to".';
        }
        if (!empty($errors)) {
            response(422, null, $errors);
        }

        $data = $service->getWorkerWorkTimeByDateRange($workerId, $from, $to);
        response(200, $data, []);

    } else if ($route === 'available-dates') {
        response(200, $service->availableDates(), []);
    } else {
        response(404, null, ['Unknown route.']);
    }
} catch (Throwable $e) {
    response(500, null, [$e->getMessage()]);
}

function isValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
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
