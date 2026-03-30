<?php

declare(strict_types=1);

$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'myuser',
    'password' => 'mypassword',
];

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function createConnection(array $config): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli = new mysqli(
            $config['host'] ?? '',
            $config['username'] ?? '',
            $config['password'] ?? ''
        );
        $mysqli->set_charset('utf8mb4');

        return $mysqli;
    } catch (mysqli_sql_exception $exception) {
        throw new RuntimeException(
            'Unable to connect to the MySQL server: ' . $exception->getMessage(),
            (int) $exception->getCode(),
            $exception
        );
    }
}

function fetchDatabases(mysqli $connection): array
{
    $excludedDatabases = [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys',
    ];

    $sql = 'SELECT SCHEMA_NAME
        FROM INFORMATION_SCHEMA.SCHEMATA
        WHERE SCHEMA_NAME NOT IN (?, ?, ?, ?)
        ORDER BY SCHEMA_NAME';

    $statement = $connection->prepare($sql);
    $statement->bind_param(
        'ssss',
        $excludedDatabases[0],
        $excludedDatabases[1],
        $excludedDatabases[2],
        $excludedDatabases[3]
    );
    $statement->execute();
    $result = $statement->get_result();

    $databases = [];
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row['SCHEMA_NAME'];
    }

    $statement->close();

    return $databases;
}

function databaseExists(mysqli $connection, string $databaseName): bool
{
    $sql = 'SELECT COUNT(*) AS database_count
        FROM INFORMATION_SCHEMA.SCHEMATA
        WHERE SCHEMA_NAME = ?';

    $statement = $connection->prepare($sql);
    $statement->bind_param('s', $databaseName);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc();
    $statement->close();

    return isset($row['database_count']) && (int) $row['database_count'] > 0;
}

function fetchTables(mysqli $connection, string $databaseName): array
{
    $sql = 'SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME';

    $statement = $connection->prepare($sql);
    $statement->bind_param('s', $databaseName);
    $statement->execute();
    $result = $statement->get_result();

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['TABLE_NAME'];
    }

    $statement->close();

    return $tables;
}

function fetchColumns(mysqli $connection, string $databaseName, string $tableName): array
{
    $sql = 'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION';

    $statement = $connection->prepare($sql);
    $statement->bind_param('ss', $databaseName, $tableName);
    $statement->execute();
    $result = $statement->get_result();

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['COLUMN_NAME']] = [
            'column_type' => $row['COLUMN_TYPE'],
            'is_nullable' => $row['IS_NULLABLE'],
            'column_default' => $row['COLUMN_DEFAULT'],
            'extra' => $row['EXTRA'],
        ];
    }

    $statement->close();

    return $columns;
}

function compareDatabases(mysqli $connection, string $databaseOne, string $databaseTwo): array
{
    $tablesOne = fetchTables($connection, $databaseOne);
    $tablesTwo = fetchTables($connection, $databaseTwo);

    $missingInDatabaseTwo = array_values(array_diff($tablesOne, $tablesTwo));
    $missingInDatabaseOne = array_values(array_diff($tablesTwo, $tablesOne));
    $commonTables = array_values(array_intersect($tablesOne, $tablesTwo));

    sort($missingInDatabaseTwo);
    sort($missingInDatabaseOne);
    sort($commonTables);

    $columnDifferences = [];

    foreach ($commonTables as $tableName) {
        $columnsOne = fetchColumns($connection, $databaseOne, $tableName);
        $columnsTwo = fetchColumns($connection, $databaseTwo, $tableName);

        $columnNamesOne = array_keys($columnsOne);
        $columnNamesTwo = array_keys($columnsTwo);

        $missingColumnsInDatabaseTwo = array_values(array_diff($columnNamesOne, $columnNamesTwo));
        $missingColumnsInDatabaseOne = array_values(array_diff($columnNamesTwo, $columnNamesOne));
        $commonColumns = array_values(array_intersect($columnNamesOne, $columnNamesTwo));

        sort($missingColumnsInDatabaseTwo);
        sort($missingColumnsInDatabaseOne);
        sort($commonColumns);

        $typeDifferences = [];
        foreach ($commonColumns as $columnName) {
            $definitionOne = normalizeColumnDefinition($columnsOne[$columnName]);
            $definitionTwo = normalizeColumnDefinition($columnsTwo[$columnName]);

            if ($definitionOne !== $definitionTwo) {
                $typeDifferences[] = [
                    'column_name' => $columnName,
                    'database_one_definition' => $definitionOne,
                    'database_two_definition' => $definitionTwo,
                ];
            }
        }

        if ($missingColumnsInDatabaseTwo !== [] || $missingColumnsInDatabaseOne !== [] || $typeDifferences !== []) {
            $columnDifferences[$tableName] = [
                'missing_in_database_two' => $missingColumnsInDatabaseTwo,
                'missing_in_database_one' => $missingColumnsInDatabaseOne,
                'type_differences' => $typeDifferences,
            ];
        }
    }

    return [
        'database_one' => $databaseOne,
        'database_two' => $databaseTwo,
        'missing_tables_in_database_two' => $missingInDatabaseTwo,
        'missing_tables_in_database_one' => $missingInDatabaseOne,
        'column_differences' => $columnDifferences,
    ];
}

function normalizeColumnDefinition(array $column): string
{
    $parts = [
        $column['column_type'] ?? '',
        ($column['is_nullable'] ?? '') === 'NO' ? 'NOT NULL' : 'NULL',
        'DEFAULT ' . (($column['column_default'] === null) ? 'NULL' : (string) $column['column_default']),
    ];

    if (($column['extra'] ?? '') !== '') {
        $parts[] = trim((string) $column['extra']);
    }

    return trim(implode(' ', $parts));
}

function buildCsvReport(array $comparison): string
{
    $stream = fopen('php://temp', 'r+');

    if ($stream === false) {
        throw new RuntimeException('Unable to create the CSV report.');
    }

    fputcsv($stream, ['Database Comparison Report']);
    fputcsv($stream, ['Database 1', $comparison['database_one']]);
    fputcsv($stream, ['Database 2', $comparison['database_two']]);
    fputcsv($stream, []);

    fputcsv($stream, ['Missing Tables']);
    fputcsv($stream, ['Category', 'Table Name']);

    foreach ($comparison['missing_tables_in_database_two'] as $tableName) {
        fputcsv($stream, ['Present in DB1, missing in DB2', $tableName]);
    }

    foreach ($comparison['missing_tables_in_database_one'] as $tableName) {
        fputcsv($stream, ['Present in DB2, missing in DB1', $tableName]);
    }

    fputcsv($stream, []);
    fputcsv($stream, ['Column Differences']);
    fputcsv($stream, ['Table', 'Difference Type', 'Column', 'DB1 Definition', 'DB2 Definition']);

    foreach ($comparison['column_differences'] as $tableName => $difference) {
        foreach ($difference['missing_in_database_two'] as $columnName) {
            fputcsv($stream, [$tableName, 'Missing in DB2', $columnName, '', '']);
        }

        foreach ($difference['missing_in_database_one'] as $columnName) {
            fputcsv($stream, [$tableName, 'Missing in DB1', $columnName, '', '']);
        }

        foreach ($difference['type_differences'] as $typeDifference) {
            fputcsv(
                $stream,
                [
                    $tableName,
                    'Definition mismatch',
                    $typeDifference['column_name'],
                    $typeDifference['database_one_definition'],
                    $typeDifference['database_two_definition'],
                ]
            );
        }
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    return $csv === false ? '' : $csv;
}
