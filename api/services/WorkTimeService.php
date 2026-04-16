<?php

declare(strict_types=1);

namespace services;

use DateTimeImmutable;
use repositories\WorkTimeRepository;
use utils\DateWindow;

require_once __DIR__ . '/../utils/DateWindow.php';

final class WorkTimeService
{
    private const int START_ACTIVITY = 2;
    private const int CORRECTION_ACTIVITY = 3;
    private const int END_ACTIVITY = 6;

    public function __construct(private readonly WorkTimeRepository $repo)
    {
    }

    public function getDailyWorkTime(string $date): array
    {
        $workTimeResult = $this->buildWorkTimeByWorkerAndDay($date, $date);

        return $this->mapWorkTimeToRows(
            $workTimeResult['work_time'],
            $workTimeResult['worker_names'],
            $date
        );
    }

    public function getWorkerWorkTimeByDateRange(int $workerId, string $from, string $to): array
    {
        $workTimeResult = $this->buildWorkTimeByWorkerAndDay($from, $to);

        $workTimeData = $workTimeResult['work_time'][$workerId] ?? [];

        $result = [];
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $dayData = $workTimeData[$day] ?? ['seconds' => 0, 'job_ids' => []];

            $result[] = [
                'worker_id' => $workerId,
                'worker_name' => $workTimeResult['worker_names'][$workerId] ?? '',
                'date' => $day,
                'job_ids' => $dayData['job_ids'],
                'duration' => $this->formatDuration($dayData['seconds']),
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return $result;
    }

    private function mapWorkTimeToRows(array $workTimeData, array $names, ?string $dateFilter = null): array
    {
        $rows = [];

        foreach ($workTimeData as $workerId => $days) {
            foreach ($days as $date => $dayData) {

                if ($dateFilter !== null && $date !== $dateFilter) {
                    continue;
                }

                $rows[] = [
                    'worker_id' => $workerId,
                    'worker_name' => $names[$workerId] ?? '',
                    'date' => $date,
                    'job_ids' => $dayData['job_ids'],
                    'duration' => $this->formatDuration($dayData['seconds']),
                ];
            }
        }

        usort($rows, fn($a, $b) => $a['worker_id'] <=> $b['worker_id']);

        return $rows;
    }

    private function buildWorkTimeByWorkerAndDay(string $from, string $to): array
    {
        [$lowerBound, $upperBound] = DateWindow::queryBounds($from, $to);
        $events = $this->repo->fetchEvents($lowerBound, $upperBound);
        $intervalMap = $this->buildWorkerDayIntervals($events, $from, $to);
        $workerNames = $this->extractWorkerNames($events);;

        $workTimeData = $this->buildWorkTimeFromIntervals($intervalMap);

        ksort($workTimeData);

        return [
            'work_time' => $workTimeData,
            'worker_names' => $workerNames
        ];
    }

    private function buildWorkTimeFromIntervals(array $intervalMap): array
    {
        $workTimeData = [];

        foreach ($intervalMap as $workerId => $days) {
            foreach ($days as $day => $intervals) {

                [$seconds, $jobIds] = $this->buildDailyWorkTime($intervals);

                $workTimeData[$workerId][$day] = [
                    'seconds' => $seconds,
                    'job_ids' => $jobIds,
                ];
            }
        }

        return $workTimeData;
    }

    private function buildDailyWorkTime(array $intervals): array
    {
        $jobIds = [];
        $rawIntervals = [];

        foreach ($intervals as [$startTs, $endTs, $jobId]) {
            $rawIntervals[] = [$startTs, $endTs];
            $jobIds[$jobId] = true;
        }

        $merged = $this->mergeIntervals($rawIntervals);

        $seconds = $this->sumIntervals($merged);
        $jobIdsList = array_map('intval', array_keys($jobIds));

        return [
            $seconds,
            $jobIdsList
        ];
    }

    private function sumIntervals(array $intervals): int
    {
        $seconds = 0;

        foreach ($intervals as [$startTs, $endTs]) {
            $seconds += max(0, $endTs - $startTs);
        }

        return $seconds;
    }

    private function extractWorkerNames(array $events): array
    {
        $names = [];

        foreach ($events as $event) {
            if (!empty($event['ime_radnika'])) {
                $names[(int)$event['id_radnika']] = (string)$event['ime_radnika'];
            }
        }

        return $names;
    }

    private function buildWorkerDayIntervals(array $events, string $from, string $to): array
    {
        $jobsData = [];
        foreach ($events as $event) {
            $jobId = (int)$event['id_posla'];
            $workerId = (int)$event['id_radnika'];
            $activity = (int)$event['id_aktivnosti'];
            $time = new DateTimeImmutable($event['datum'] . ' ' . $event['vreme']);
            $unix = $time->getTimestamp();

            $jobsData[$jobId] ??= [
                'starts' => [],
                'endTimes' => [],
                'finishers' => [],
            ];

            if ($activity === self::START_ACTIVITY) {
                $jobsData[$jobId]['starts'][$workerId] = $unix;
                continue;
            }

            if ($activity === self::CORRECTION_ACTIVITY) {
                unset($jobsData[$jobId]['starts'][$workerId]);
                continue;
            }

            if ($activity === self::END_ACTIVITY) {
                $jobsData[$jobId]['endTimes'][] = $unix;
                $jobsData[$jobId]['finishers'][$workerId] = true;
            }
        }

        $intervals = [];
        foreach ($jobsData as $jobId => $job) {
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
            
            $workers = array_unique(array_merge(
                array_keys($job['starts']),
                array_keys($job['finishers'])
            ));

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

        usort($intervals, static fn(array $a, array $b): int => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));

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

    public function availableDates(): array
    {
        return $this->repo->availableDates();
    }
}
