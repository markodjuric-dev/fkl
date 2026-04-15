<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/WorkTimeService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, null, ['Only GET is supported.']);
}

$route = trim((string) ($_GET['route'] ?? ''));
$service = new WorkTimeService(db());

try {
    if ($route === 'daily-workers') {
        $date = trim((string) ($_GET['date'] ?? ''));
        if (!isValidDate($date)) {
            respond(422, null, ['Parameter "date" must be in format YYYY-MM-DD.']);
        }

        $data = $service->dailyWorkers($date);
        respond(200, $data, []);
    }

    if ($route === 'worker-range') {
        $workerIdRaw = trim((string) ($_GET['worker_id'] ?? ''));
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $errors = [];

        if ($workerIdRaw === '' || !ctype_digit($workerIdRaw)) {
            $errors[] = 'Parameter "worker_id" is required and must be numeric.';
        }
        if (!isValidDate($from)) {
            $errors[] = 'Parameter "from" must be in format YYYY-MM-DD.';
        }
        if (!isValidDate($to)) {
            $errors[] = 'Parameter "to" must be in format YYYY-MM-DD.';
        }
        if (isValidDate($from) && isValidDate($to) && $from > $to) {
            $errors[] = 'Parameter "from" must be less than or equal to "to".';
        }
        if ($errors !== []) {
            respond(422, null, $errors);
        }

        $data = $service->workerRange((int) $workerIdRaw, $from, $to);
        respond(200, $data, []);
    }

    respond(404, null, ['Unknown route. Supported routes: daily-workers, worker-range.']);
} catch (Throwable $e) {
    respond(500, null, [$e->getMessage()]);
}

function isValidDate(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function respond(int $status, mixed $data, array $errors): never
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
