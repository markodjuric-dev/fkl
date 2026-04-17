<?php

namespace services;

final class WorkTimeCalculator
{
    public function calculate(array $workerIntervalMap): array
    {
        $workTimeData = [];

        foreach ($workerIntervalMap as $workerId => $days) {
            foreach ($days as $day => $intervals) {

                [$seconds, $jobIds] = $this->calculateDailyTotals($intervals);

                $workTimeData[$workerId][$day] = [
                    'seconds' => $seconds,
                    'job_ids' => $jobIds,
                ];
            }
        }

        return $workTimeData;
    }

    private function calculateDailyTotals(array $intervals): array
    {
        $jobIds = [];
        $timePeriods = [];

        foreach ($intervals as [$startTs, $endTs, $jobId]) {
            $timePeriods[] = [$startTs, $endTs];
            $jobIds[$jobId] = true;
        }

        $mergedPeriods = $this->mergeOverlappingIntervals($timePeriods);
        $totalSeconds = $this->sumTotalDuration($mergedPeriods);

        $jobIdList = array_keys($jobIds);

        return [
            $totalSeconds,
            $jobIdList
        ];
    }

    private function mergeOverlappingIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn(array $a, array $b): int => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));

        $merged = [$intervals[0]];
        $current = 0;

        for ($i = 1, $count = count($intervals); $i < $count; $i++) {
            [$nextStart, $nextEnd] = $intervals[$i];
            [, $lastEnd] = $merged[$current];

            if ($nextStart <= $lastEnd) {
                $merged[$current][1] = max($lastEnd, $nextEnd);
            } else {
                $merged[] = [$nextStart, $nextEnd];
                $current++;
            }
        }

        return $merged;
    }

    private function sumTotalDuration(array $intervals): int
    {
        $total = 0;

        foreach ($intervals as [$startTs, $endTs]) {
            $total += max(0, $endTs - $startTs);
        }

        return $total;
    }
}
