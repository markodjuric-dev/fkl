<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/DateWindow.php';

final class WorkTimeService
{
    private const int START_ACTIVITY = 2;
    private const int CORRECTION_ACTIVITY = 3;
    private const int END_ACTIVITY = 6;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dailyWorkers(string $date): array
    {
        $range = $this->buildRangeStats($date, $date);
        $stats = $range['stats'];
        $names = $range['names'];
        $rows = [];
        foreach ($stats as $workerId => $days) {
            $dayData = $days[$date] ?? ['seconds' => 0, 'job_ids' => []];
            $rows[] = [
                'worker_id' => (int) $workerId,
                'worker_name' => $names[(int) $workerId] ?? '',
                'date' => $date,
                'job_ids' => $dayData['job_ids'],
                'duration' => $this->formatDuration((int) $dayData['seconds']),
            ];
        }
        usort(
            $rows,
            static fn (array $a, array $b): int => $a['worker_id'] <=> $b['worker_id']
        );
        return $rows;
    }

    public function workerRange(int $workerId, string $from, string $to): array
    {
        $range = $this->buildRangeStats($from, $to);
        $stats = $range['stats'];
        $names = $range['names'];
        $workerStats = $stats[$workerId] ?? [];
        $result = [];
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $dayData = $workerStats[$day] ?? ['seconds' => 0, 'job_ids' => []];
            $result[] = [
                'worker_id' => $workerId,
                'worker_name' => $names[$workerId] ?? '',
                'date' => $day,
                'job_ids' => $dayData['job_ids'],
                'duration' => $this->formatDuration((int) $dayData['seconds']),
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $result;
    }

    private function buildRangeStats(string $from, string $to): array
    {
        [$lowerBound, $upperBound] = DateWindow::queryBounds($from, $to);
        $events = $this->fetchEvents($lowerBound, $upperBound);
        $intervalMap = $this->buildIntervalsPerWorkerPerDay($events, $from, $to);
        $workerIds = [];
        $workerNames = [];

        foreach ($events as $event) {
            $id = (int) $event['id_radnika'];
            $workerIds[$id] = true;

            if (!empty($event['ime_radnika'])) {
                $workerNames[$id] = (string) $event['ime_radnika'];
            }
        }

        $stats = [];
        foreach ($intervalMap as $workerId => $days) {
            foreach ($days as $day => $intervals) {
                $jobIds = [];
                $planIntervals = [];

                foreach ($intervals as [$startTs, $endTs, $jobId]) {
                    $planIntervals[] = [$startTs, $endTs];
                    $jobIds[$jobId] = true;
                }

                $merged = $this->mergeIntervals($planIntervals);
                $seconds = 0;
                foreach ($merged as [$startTs, $endTs]) {
                    $seconds += max(0, $endTs - $startTs);
                }

                $ids = array_map('intval', array_keys($jobIds));
                sort($ids);

                $stats[$workerId][$day] = [
                    'seconds' => $seconds,
                    'job_ids' => $ids,
                ];
            }
        }

        foreach (array_keys($workerIds) as $workerId) {
            $stats[$workerId] ??= [];
        }

        ksort($stats);
        return [
            'stats' => $stats,
            'names' => $workerNames
        ];
    }

    private function fetchEvents(string $lowerBound, string $upperBound): array
    {
        $sql = <<<'SQL'
        SELECT
            id_posla,
            id_radnika,
            ime_radnika,
            id_aktivnosti,
            datum,
            vreme
        FROM test_log_r
        WHERE CONCAT(datum, ' ', vreme) BETWEEN :lower_bound AND :upper_bound
        AND id_aktivnosti IN (2, 3, 6)
        ORDER BY datum, vreme, id_posla, id_radnika
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'lower_bound' => $lowerBound,
            'upper_bound' => $upperBound,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function buildIntervalsPerWorkerPerDay(array $events, string $from, string $to): array
    {
        $jobs = [];
        foreach ($events as $event) {
            $jobId = (int) $event['id_posla'];
            $workerId = (int) $event['id_radnika'];
            $activity = (int) $event['id_aktivnosti'];
            $time = new DateTimeImmutable($event['datum'] . ' ' . $event['vreme']);
            $unix = $time->getTimestamp();

            $jobs[$jobId] ??= [
                'starts' => [],
                'endTimes' => [],
                'finishers' => [],
            ];

            if ($activity === self::START_ACTIVITY) {
                $jobs[$jobId]['starts'][$workerId] = $unix;
                continue;
            }

            if ($activity === self::CORRECTION_ACTIVITY) {
                unset($jobs[$jobId]['starts'][$workerId]);
                continue;
            }

            if ($activity === self::END_ACTIVITY) {
                $jobs[$jobId]['endTimes'][] = $unix;
                $jobs[$jobId]['finishers'][$workerId] = true;
            }
        }

        $intervals = [];
        foreach ($jobs as $jobId => $job) {
            if ($job['starts'] === [] || $job['endTimes'] === []) {
                continue;
            }

            $startTs = min($job['starts']);
            $endTs = max($job['endTimes']);
            $jobIds = $jobId;
            if ($endTs <= $startTs) {
                continue;
            }

            $logicalDay = DateWindow::logicalWorkdayForStart((new DateTimeImmutable())->setTimestamp($startTs));
            if ($logicalDay < $from || $logicalDay > $to) {
                continue;
            }

            $workers = array_map('intval', array_keys($job['starts'] + $job['finishers']));

            foreach ($workers as $workerId) {
                $intervals[$workerId][$logicalDay][] = [$startTs, $endTs, $jobIds];
            }
        }

        return $intervals;
    }

    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (array $a, array $b): int => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));

        $merged = [$intervals[0]];
        $lastIndex = 0;
        for ($i = 1, $n = count($intervals); $i < $n; $i++) {
            [$start, $end] = $intervals[$i];
            [$mStart, $mEnd] = $merged[$lastIndex];

            if ($start <= $mEnd) {
                $merged[$lastIndex] = [$mStart, max($mEnd, $end)];
            } else {
                $merged[] = [$start, $end];
                $lastIndex++;
            }
        }

        return $merged;
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
