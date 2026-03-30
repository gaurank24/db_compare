<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$errorMessage = '';
$comparison = null;
$databaseOne = trim((string) ($_REQUEST['database_one'] ?? ''));
$databaseTwo = trim((string) ($_REQUEST['database_two'] ?? ''));

try {
    if ($databaseOne === '' || $databaseTwo === '') {
        throw new InvalidArgumentException('Please select both databases before running the comparison.');
    }

    if ($databaseOne === $databaseTwo) {
        throw new InvalidArgumentException('Please choose two different databases to compare.');
    }

    $connection = createConnection($dbConfig);

    if (!databaseExists($connection, $databaseOne)) {
        throw new InvalidArgumentException('Database 1 does not exist on the MySQL server.');
    }

    if (!databaseExists($connection, $databaseTwo)) {
        throw new InvalidArgumentException('Database 2 does not exist on the MySQL server.');
    }

    $comparison = compareDatabases($connection, $databaseOne, $databaseTwo);

    if (isset($_GET['download']) && $_GET['download'] === 'csv') {
        $csv = buildCsvReport($comparison);
        $filename = 'db-compare-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $databaseOne . '-vs-' . $databaseTwo) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        $connection->close();
        exit;
    }

    $connection->close();
} catch (Throwable $throwable) {
    $errorMessage = $throwable->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparison Result</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #d7deea;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;
            --success-bg: #ecfdf5;
            --success-text: #166534;
            --warning-bg: #fff7ed;
            --warning-text: #c2410c;
            --table-head: #edf6f5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #eef4ff 0%, var(--bg) 100%);
            color: var(--text);
        }

        .container {
            max-width: 1180px;
            margin: 40px auto;
            padding: 0 20px 40px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 28px;
            margin-bottom: 24px;
        }

        h1,
        h2,
        h3 {
            margin-top: 0;
        }

        p {
            color: var(--muted);
            line-height: 1.5;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .button {
            display: inline-block;
            text-decoration: none;
            background: var(--primary);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: bold;
        }

        .button.secondary {
            background: #475569;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .message {
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid transparent;
        }

        .message.error {
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: #fecaca;
        }

        .message.success {
            background: var(--success-bg);
            color: var(--success-text);
            border-color: #bbf7d0;
        }

        .message.warning {
            background: var(--warning-bg);
            color: var(--warning-text);
            border-color: #fed7aa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            border: 1px solid var(--border);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: var(--table-head);
        }

        .diff-missing {
            background: #fff7ed;
        }

        .diff-type {
            background: #eff6ff;
        }

        .muted {
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Database Comparison Result</h1>

            <?php if ($errorMessage !== ''): ?>
                <div class="message error"><?php echo escape($errorMessage); ?></div>
            <?php elseif ($comparison !== null): ?>
                <p>
                    Comparing <strong><?php echo escape($comparison['database_one']); ?></strong>
                    with <strong><?php echo escape($comparison['database_two']); ?></strong>.
                </p>

                <div class="actions">
                    <a class="button secondary" href="index.php">Back</a>
                    <a
                        class="button"
                        href="compare.php?database_one=<?php echo urlencode($comparison['database_one']); ?>&database_two=<?php echo urlencode($comparison['database_two']); ?>&download=csv"
                    >
                        Download CSV Report
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($comparison !== null && $errorMessage === ''): ?>
            <div class="grid">
                <div class="card">
                    <h2>Missing Tables</h2>

                    <h3>Present in <?php echo escape($comparison['database_one']); ?>, Missing in <?php echo escape($comparison['database_two']); ?></h3>
                    <?php if ($comparison['missing_tables_in_database_two'] === []): ?>
                        <div class="message success">No missing tables found in <?php echo escape($comparison['database_two']); ?>.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>S. No.</th>
                                    <th>Table Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparison['missing_tables_in_database_two'] as $index => $tableName): ?>
                                    <tr class="diff-missing">
                                        <td><?php echo escape((string) ($index + 1)); ?></td>
                                        <td><?php echo escape($tableName); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <h3 style="margin-top: 24px;">Present in <?php echo escape($comparison['database_two']); ?>, Missing in <?php echo escape($comparison['database_one']); ?></h3>
                    <?php if ($comparison['missing_tables_in_database_one'] === []): ?>
                        <div class="message success">No missing tables found in <?php echo escape($comparison['database_one']); ?>.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>S. No.</th>
                                    <th>Table Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparison['missing_tables_in_database_one'] as $index => $tableName): ?>
                                    <tr class="diff-missing">
                                        <td><?php echo escape((string) ($index + 1)); ?></td>
                                        <td><?php echo escape($tableName); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Column Differences</h2>

                    <?php if ($comparison['column_differences'] === []): ?>
                        <div class="message success">No column differences were found for common tables.</div>
                    <?php else: ?>
                        <?php foreach ($comparison['column_differences'] as $tableName => $difference): ?>
                            <h3><?php echo escape($tableName); ?></h3>

                            <?php if (
                                $difference['missing_in_database_two'] === [] &&
                                $difference['missing_in_database_one'] === [] &&
                                $difference['type_differences'] === []
                            ): ?>
                                <p class="muted">No differences found.</p>
                            <?php endif; ?>

                            <?php if ($difference['missing_in_database_two'] !== [] || $difference['missing_in_database_one'] !== []): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>S. No.</th>
                                            <th>Difference</th>
                                            <th>Column Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $serialNumber = 1; ?>
                                        <?php foreach ($difference['missing_in_database_two'] as $columnName): ?>
                                            <tr class="diff-missing">
                                                <td><?php echo escape((string) $serialNumber++); ?></td>
                                                <td>Present in <?php echo escape($comparison['database_one']); ?>, missing in <?php echo escape($comparison['database_two']); ?></td>
                                                <td><?php echo escape($columnName); ?></td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php foreach ($difference['missing_in_database_one'] as $columnName): ?>
                                            <tr class="diff-missing">
                                                <td><?php echo escape((string) $serialNumber++); ?></td>
                                                <td>Present in <?php echo escape($comparison['database_two']); ?>, missing in <?php echo escape($comparison['database_one']); ?></td>
                                                <td><?php echo escape($columnName); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php if ($difference['type_differences'] !== []): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>S. No.</th>
                                            <th>Column Name</th>
                                            <th><?php echo escape($comparison['database_one']); ?> Definition</th>
                                            <th><?php echo escape($comparison['database_two']); ?> Definition</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($difference['type_differences'] as $index => $typeDifference): ?>
                                            <tr class="diff-type">
                                                <td><?php echo escape((string) ($index + 1)); ?></td>
                                                <td><?php echo escape($typeDifference['column_name']); ?></td>
                                                <td><?php echo escape($typeDifference['database_one_definition']); ?></td>
                                                <td><?php echo escape($typeDifference['database_two_definition']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
