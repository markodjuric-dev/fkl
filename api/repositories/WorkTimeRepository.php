<?php

namespace repositories;

use PDO;

readonly class WorkTimeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function fetchEvents(string $lowerBound, string $upperBound): array
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

    public function availableDates(): array
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

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll() ?: [];
        return array_values(array_filter(array_map(
            static fn(array $row): string => (string)($row['work_date'] ?? ''),
            $rows
        )));
    }
}