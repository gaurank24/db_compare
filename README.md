# MySQL Database Compare Tool

A lightweight Core PHP web application to compare the structure of two MySQL databases on the same server.

It helps you quickly find:

- Missing tables in either database
- Missing columns in common tables
- Column definition mismatches for common columns
- A downloadable CSV report of the comparison

## Features

- Pure Core PHP, no framework
- Uses `mysqli` and `INFORMATION_SCHEMA`
- Fetches all available non-system databases from the configured MySQL server
- Simple web UI with two database dropdowns
- Compares:
  - Tables present in DB1 but missing in DB2
  - Tables present in DB2 but missing in DB1
  - Missing columns in common tables
  - Column definition differences
- Serial numbers in all result tables
- CSV export for the comparison report
- Basic error handling for connection and invalid database selection
- Color highlighting for differences

## Requirements

- PHP 8.0+ recommended
- MySQL or MariaDB server
- PHP `mysqli` extension enabled
- A MySQL user with permission to read:
  - `INFORMATION_SCHEMA.SCHEMATA`
  - `INFORMATION_SCHEMA.TABLES`
  - `INFORMATION_SCHEMA.COLUMNS`

## Project Structure

- `config.php`  
  Stores MySQL connection credentials and shared helper functions.

- `index.php`  
  Loads the database list and shows the compare form.

- `compare.php`  
  Processes the selected databases, compares schema details, renders results, and supports CSV export.

## Configuration

Edit the credentials in `config.php`:

```php
$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'myuser',
    'password' => 'mypassword',
];
```

Note:

- The tool compares databases available on the same MySQL server.
- System databases are excluded from the dropdown list:
  - `information_schema`
  - `mysql`
  - `performance_schema`
  - `sys`

## How To Run

From the project directory:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/index.php
```

## How To Use

1. Open the app in your browser.
2. Select `Database 1`.
3. Select `Database 2`.
4. Click `Compare`.
5. Review:
   - Missing tables
   - Missing columns
   - Column definition mismatches
6. Optionally click `Download CSV Report`.

## What Gets Compared

### 1. Tables

The app lists:

- Tables present in DB1 but missing in DB2
- Tables present in DB2 but missing in DB1

### 2. Columns

For tables that exist in both databases, the app checks:

- Columns missing in DB1
- Columns missing in DB2
- Column definition differences

The column definition comparison currently includes:

- Column type
- Nullability
- Default value
- Extra attributes

## Output

The result page shows clean HTML tables with:

- Serial number
- Table or column name
- Difference description
- Definition values for mismatched columns

Differences are also highlighted with colors for easier scanning.

## Error Handling

The app handles common issues such as:

- Invalid MySQL credentials
- Server connection errors
- Missing or invalid database names
- Comparing the same database against itself

## CSV Export

The comparison result can be downloaded as a CSV file from the result page.

The export includes:

- Compared database names
- Missing tables
- Missing columns
- Column definition mismatches

## Example Use Cases

- Comparing development and production schemas
- Checking staging vs production before deployment
- Verifying migration results
- Auditing structural drift between databases

## Notes

- This tool compares database structure only. It does not compare row data.
- It currently focuses on tables and columns, not indexes, foreign keys, triggers, or stored procedures.
- The app is intentionally simple and built for quick visual checks.
