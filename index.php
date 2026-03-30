<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$databases = [];
$errorMessage = '';

try {
    $connection = createConnection($dbConfig);
    $databases = fetchDatabases($connection);
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
    <title>MySQL Database Compare Tool</title>
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
            max-width: 960px;
            margin: 48px auto;
            padding: 0 20px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 30px;
        }

        p {
            color: var(--muted);
            line-height: 1.5;
        }

        form {
            margin-top: 24px;
            display: grid;
            gap: 18px;
        }

        .field-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        button {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 12px 14px;
            font-size: 15px;
        }

        select {
            background: #fff;
        }

        button {
            background: var(--primary);
            border: none;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button:hover {
            background: var(--primary-dark);
        }

        .message {
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 18px;
            border: 1px solid transparent;
        }

        .message.error {
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: #fecaca;
        }

        .message.info {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>MySQL Database Compare Tool</h1>
            <p>Select any two databases from the same MySQL server to compare missing tables and column mismatches.</p>

            <?php if ($errorMessage !== ''): ?>
                <div class="message error"><?php echo escape($errorMessage); ?></div>
            <?php elseif ($databases === []): ?>
                <div class="message info">No user databases were found on this MySQL server.</div>
            <?php else: ?>
                <form action="compare.php" method="post">
                    <div class="field-row">
                        <div>
                            <label for="database_one">Database 1</label>
                            <select name="database_one" id="database_one" required>
                                <option value="">Select a database</option>
                                <?php foreach ($databases as $database): ?>
                                    <option value="<?php echo escape($database); ?>"><?php echo escape($database); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="database_two">Database 2</label>
                            <select name="database_two" id="database_two" required>
                                <option value="">Select a database</option>
                                <?php foreach ($databases as $database): ?>
                                    <option value="<?php echo escape($database); ?>"><?php echo escape($database); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <button type="submit">Compare</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
