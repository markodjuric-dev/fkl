<?php

namespace services;

use utils\TimeFormatter;

require_once __DIR__ . '/../utils/TimeFormatter.php';

final class WorkTimeResponseBuilder
{
    public function buildRows(
        array   $workTimeData,
        array   $workerNames,
        ?string $dateFilter = null
    ): array
    {
        $rows = [];

        foreach ($workTimeData as $workerId => $days) {
            foreach ($days as $date => $dayData) {
                if ($dateFilter !== null && $date !== $dateFilter) {
                    continue;
                }

                $rows[] = $this->formatRow(
                    (string)$workerId,
                    $date,
                    $dayData,
                    $workerNames
                );
            }
        }

        usort($rows, fn($a, $b) => strcmp($a['worker_id'], $b['worker_id']));

        return $rows;
    }

    public function buildDailyReportForWorker(
        string $workerId,
        array  $workerNames,
        array  $workTimeData,
        string $from,
        string $to
    ): array
    {
        $rows = [];
        $currentDate = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);

        while ($currentDate <= $end) {
            $formattedDate = $currentDate->format('Y-m-d');

            $dayData = $workTimeData[$workerId][$formattedDate]
                ?? $workTimeData[(int)$workerId][$formattedDate]
                ?? ['seconds' => 0, 'job_ids' => []];

            $rows[] = $this->formatRow(
                $workerId,
                $formattedDate,
                $dayData,
                $workerNames
            );

            $currentDate = $currentDate->modify('+1 day');
        }

        return $rows;
    }

    private function formatRow(string $workerId, string $date, array $dayData, array $names): array
    {
        $formattedDate = (new \DateTimeImmutable($date))->format('d.m.Y.');

        return [
            'worker_id' => $workerId,
            'worker_name' => $names[$workerId] ?? $names[(int)$workerId] ?? '',
            'date' => $formattedDate,
            'job_ids' => $dayData['job_ids'] ?? [],
            'duration' => TimeFormatter::secondsToHms($dayData['seconds'] ?? 0),
        ];
    }
}