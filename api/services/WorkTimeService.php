<?php

declare(strict_types=1);

namespace services;

use enum\ActivityType;
use repositories\WorkTimeRepository;
use utils\DateWindow;

require_once __DIR__ . '/../utils/DateWindow.php';
require_once __DIR__ . '/../enum/ActivityType.php';

final readonly class WorkTimeService
{
    public function __construct(
        private WorkTimeRepository      $repo,
        private WorkTimeCalculator      $calculator,
        private WorkTimeResponseBuilder $responseBuilder,
    )
    {
    }

    public function getDailyWorkTime(string $date): array
    {
        [$start, $end] = DateWindow::queryBounds($date, $date);

        $activities = $this->repo->findActivities($start, $end);

        $intervalMap = $this->buildWorkerDayIntervals($activities);
        $workTimeData = $this->calculator->calculate($intervalMap);

        return $this->responseBuilder->buildRows(
            $workTimeData,
            $this->extractWorkerNames($activities),
            $date
        );
    }

    public function getWorkerWorkTimeByDateRange(string $workerId, string $from, string $to): array
    {
        $workerIdFormatted = str_pad((string)$workerId, 4, '0', STR_PAD_LEFT);

        [$start, $end] = DateWindow::queryBounds($from, $to);

        $activities = $this->repo->fetchActivityByWorkerAndRange(
            $workerIdFormatted,
            $start,
            $end
        );

        $intervalMap = $this->buildWorkerDayIntervals($activities);
        $workTimeData = $this->calculator->calculate($intervalMap);

        return $this->responseBuilder->buildDailyReportForWorker(
            $workerIdFormatted,
            $this->extractWorkerNames($activities),
            $workTimeData,
            $from,
            $to
        );
    }

    private function extractWorkerNames(array $activities): array
    {
        $names = [];
        foreach ($activities as $activity) {
            $id = $activity['id_radnika'];
            $name = $activity['ime_radnika'] ?? '';

            if (!empty($name) && !isset($names[$id])) {
                $names[$id] = $name;
            }
        }
        return $names;
    }

    private function buildWorkerDayIntervals(array $activities): array
    {
        $jobs = [];

        foreach ($activities as $a) {

            $jobId = $a['id_posla'];
            $workerId = (string)$a['id_radnika'];
            $type = ActivityType::from((int)$a['id_aktivnosti']);
            $ts = (new \DateTimeImmutable($a['datum'] . ' ' . $a['vreme']))->getTimestamp();

            $jobs[$jobId] ??= [
                'starts' => [],
                'ends' => [],
                'workers' => [],
            ];

            if ($type->isStart()) {
                $jobs[$jobId]['starts'][$workerId] = $ts;
                $jobs[$jobId]['workers'][$workerId] = true;
            }

            if ($type->isCorrection()) {
                unset($jobs[$jobId]['starts'][$workerId]);
            }

            if ($type->isEnd()) {
                $jobs[$jobId]['ends'][] = $ts;
                $jobs[$jobId]['workers'][$workerId] = true;
            }
        }

        return $this->toIntervalMap($jobs);
    }

    private function toIntervalMap(array $jobs): array
    {
        $result = [];

        foreach ($jobs as $jobId => $job) {
            if (empty($job['starts']) || empty($job['ends'])) {
                continue;
            }

            $start = min($job['starts']);
            $end = max($job['ends']);

            if ($end <= $start) continue;

            $dtStart = (new \DateTimeImmutable())->setTimestamp($start);
            $workDay = DateWindow::logicalWorkdayForStart($dtStart);

            foreach (array_keys($job['workers']) as $workerId) {
                $result[$workerId][$workDay][] = [
                    $start,
                    $end,
                    $jobId
                ];
            }
        }

        return $result;
    }

    public function availableDates(): array
    {
        return $this->repo->fetchAvailableWorkDates();
    }
}
