<?php

namespace repositories;

use PDO;

readonly class WorkTimeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findActivities(string $start, string $end): array
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
        WHERE datum BETWEEN DATE(:start) AND DATE(:end)
        AND id_aktivnosti IN (2, 3, 6)
        ORDER BY datum, vreme, id_posla, id_radnika
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'start' => $start,
            'end' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchActivityByWorkerAndRange(string $workerId, string $start, string $end): array
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
        WHERE id_posla IN (
            SELECT DISTINCT id_posla 
            FROM test_log_r 
            WHERE id_radnika = :worker_id 
              AND datum BETWEEN DATE(:start) AND DATE(:end)
        )
        AND id_aktivnosti IN (2, 3, 6)
        ORDER BY id_posla ASC, datum ASC, vreme ASC, id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'worker_id' => $workerId,
            'start' => $start,
            'end' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchAvailableWorkDates(): array
    {
        $sql = <<<'SQL'
        SELECT DISTINCT
            CASE
                WHEN TIME(vreme) < '06:00:00' THEN DATE_SUB(datum, INTERVAL 1 DAY)
                ELSE datum
            END AS work_date
        FROM test_log_r
        ORDER BY work_date
        SQL;

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}