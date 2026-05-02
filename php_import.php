<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

ini_set('memory_limit', '-1');
set_time_limit(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DEFAULT_BATCH_SIZE = 500;

main($argv);

function main(array $argv): void
{
    $options = parseOptions($argv);

    if (!is_file($options['file'])) {
        fail("Dump file not found: {$options['file']}");
    }

    $connection = connectDatabase($options);

    if ($options['drop-db']) {
        $connection->query('DROP DATABASE IF EXISTS `' . escapeIdentifier($options['db']) . '`');
        out("Dropped database {$options['db']}");
    }

    if ($options['create-db']) {
        $connection->query('CREATE DATABASE IF NOT EXISTS `' . escapeIdentifier($options['db']) . '` CHARACTER SET ' . $options['charset'] . ' COLLATE ' . charsetToCollation($options['charset']));
        out("Ensured database {$options['db']} exists");
    }

    $connection->select_db($options['db']);
    $connection->set_charset($options['charset']);
    $connection->query('SET SESSION foreign_key_checks = 0');
    $connection->query('SET SESSION unique_checks = 0');
    $connection->query('SET SESSION sql_log_bin = 0');

    importDump($connection, $options['file'], (int) $options['batch']);

    $connection->query('SET SESSION foreign_key_checks = 1');
    $connection->query('SET SESSION unique_checks = 1');

    out('Import completed successfully.');
}

function parseOptions(array $argv): array
{
    $raw = getopt('', [
        'file:',
        'host::',
        'port::',
        'user::',
        'pass::',
        'db::',
        'charset::',
        'drop-db',
        'create-db',
        'batch::',
        'help',
    ]);

    if (isset($raw['help']) || !isset($raw['file'])) {
        printHelp();
        exit(isset($raw['help']) ? 0 : 1);
    }

    return [
        'file' => normalizePath((string) $raw['file']),
        'host' => (string) ($raw['host'] ?? '127.0.0.1'),
        'port' => (int) ($raw['port'] ?? 3306),
        'user' => (string) ($raw['user'] ?? 'root'),
        'pass' => (string) ($raw['pass'] ?? ''),
        'db' => (string) ($raw['db'] ?? 'vietbainn_nasani'),
        'charset' => (string) ($raw['charset'] ?? 'utf8mb4'),
        'drop-db' => isset($raw['drop-db']),
        'create-db' => isset($raw['create-db']),
        'batch' => max(1, (int) ($raw['batch'] ?? DEFAULT_BATCH_SIZE)),
    ];
}

function printHelp(): void
{
    $help = <<<'TXT'
PHP MySQL importer for large .sql / .sql.gz dumps.

Usage:
  php php_import.php --file=/path/to/dump.sql.gz [options]

Options:
  --file=PATH        Path to .sql or .sql.gz dump file (required)
  --host=HOST        MySQL host (default: 127.0.0.1)
  --port=PORT        MySQL port (default: 3306)
  --user=USER        MySQL username (default: root)
  --pass=PASS        MySQL password (default: empty)
  --db=NAME          Database name (default: vietbainn_nasani)
  --charset=CHARSET  Connection charset (default: utf8mb4)
  --drop-db          Drop database before import
  --create-db        Create database before import if missing
  --batch=NUM        Progress output frequency by statements (default: 500)
  --help             Show this help

Examples:
  php php_import.php --file=./newvaupost.sql --host=127.0.0.1 --user=root --pass=root --db=newvaupost --create-db
  php php_import.php --file=./backup.sql --db=mydb --drop-db --create-db
TXT;

    out($help);
}

function connectDatabase(array $options): mysqli
{
    $mysqli = mysqli_init();
    $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1);
    $mysqli->real_connect(
        $options['host'],
        $options['user'],
        $options['pass'],
        null,
        $options['port']
    );

    return $mysqli;
}

function importDump(mysqli $connection, string $filePath, int $batchSize): void
{
    $isGzip = str_ends_with(strtolower($filePath), '.gz');
    $reader = $isGzip ? openGzip($filePath) : openFile($filePath);

    $delimiter = ';';
    $statement = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $lineNumber = 0;
    $statementCount = 0;
    $startedAt = microtime(true);
    $compressedSize = filesize($filePath) ?: 0;

    out('Starting import: ' . $filePath);

    while (($line = readDumpLine($reader, $isGzip)) !== null) {
        $lineNumber++;

        if ($statement === '') {
            $trimmed = ltrim($line);

            if ($trimmed === '' || $trimmed === "\n" || $trimmed === "\r\n") {
                continue;
            }

            if (preg_match('/^\s*(--|#)/', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^\s*\/\*!.*\*\/\s*;?\s*$/s', trim($line)) === 1) {
                executeStatement($connection, trim($line), $lineNumber);
                $statementCount++;
                reportProgress($statementCount, $batchSize, $startedAt, $filePath, $compressedSize, $reader, $isGzip);
                continue;
            }

            if (preg_match('/^\s*DELIMITER\s+(.+)\s*$/i', trim($line), $matches) === 1) {
                $delimiter = $matches[1];
                continue;
            }
        }

        $statement .= $line;

        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $stringChar) {
                    $inString = false;
                    $stringChar = '';
                }

                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $inString = true;
                $stringChar = $char;
                $escaped = false;
            }
        }

        if ($inString) {
            continue;
        }

        if (statementEndsWithDelimiter($statement, $delimiter)) {
            $sql = trim(substr($statement, 0, -strlen($delimiter)));
            $statement = '';

            if ($sql === '') {
                continue;
            }

            executeStatement($connection, $sql, $lineNumber);
            $statementCount++;
            reportProgress($statementCount, $batchSize, $startedAt, $filePath, $compressedSize, $reader, $isGzip);
        }
    }

    if (trim($statement) !== '') {
        executeStatement($connection, trim($statement), $lineNumber);
        $statementCount++;
    }

    $duration = microtime(true) - $startedAt;
    out(sprintf('Executed %d statements in %.2f seconds.', $statementCount, $duration));

    closeReader($reader, $isGzip);
}

function executeStatement(mysqli $connection, string $sql, int $lineNumber): void
{
    try {
        $connection->query($sql);
    } catch (Throwable $e) {
        $preview = preg_replace('/\s+/', ' ', substr($sql, 0, 220));
        fail("Import failed near line {$lineNumber}: {$e->getMessage()}\nSQL preview: {$preview}");
    }
}

function reportProgress(int $statementCount, int $batchSize, float $startedAt, string $filePath, int $compressedSize, $reader, bool $isGzip): void
{
    if ($statementCount % $batchSize !== 0) {
        return;
    }

    $elapsed = max(1, microtime(true) - $startedAt);
    $rate = $statementCount / $elapsed;
    $progress = estimateProgress($compressedSize, $reader, $isGzip);

    out(sprintf(
        '[%s] statements=%d rate=%.1f stmt/s%s',
        date('H:i:s'),
        $statementCount,
        $rate,
        $progress === null ? '' : ' progress=' . $progress
    ));
}

function estimateProgress(int $compressedSize, $reader, bool $isGzip): ?string
{
    if ($compressedSize <= 0) {
        return null;
    }

    $position = $isGzip ? gztell($reader) : ftell($reader);
    if (!is_int($position) || $position < 0) {
        return null;
    }

    $percent = min(100, max(0, ($position / $compressedSize) * 100));
    return number_format($percent, 1) . '%';
}

function statementEndsWithDelimiter(string $statement, string $delimiter): bool
{
    $trimmed = rtrim($statement);
    return $trimmed !== '' && str_ends_with($trimmed, $delimiter);
}

function openGzip(string $filePath)
{
    if (!function_exists('gzopen')) {
        fail('PHP zlib extension is required for .gz import.');
    }

    $handle = gzopen($filePath, 'rb');
    if ($handle === false) {
        fail('Unable to open gzip file: ' . $filePath);
    }

    return $handle;
}

function openFile(string $filePath)
{
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        fail('Unable to open file: ' . $filePath);
    }

    return $handle;
}

function readDumpLine($reader, bool $isGzip): ?string
{
    if ($isGzip) {
        if (gzeof($reader)) {
            return null;
        }

        $line = gzgets($reader);
        return $line === false ? null : $line;
    }

    if (feof($reader)) {
        return null;
    }

    $line = fgets($reader);
    return $line === false ? null : $line;
}

function closeReader($reader, bool $isGzip): void
{
    if ($isGzip) {
        gzclose($reader);
        return;
    }

    fclose($reader);
}

function normalizePath(string $path): string
{
    if ($path === '') {
        return $path;
    }

    if ($path[0] === '~') {
        $home = getenv('HOME') ?: '';
        return $home . substr($path, 1);
    }

    return $path;
}

function escapeIdentifier(string $value): string
{
    return str_replace('`', '``', $value);
}

function charsetToCollation(string $charset): string
{
    return match ($charset) {
        'utf8mb4' => 'utf8mb4_unicode_ci',
        'utf8' => 'utf8_unicode_ci',
        default => $charset . '_general_ci',
    };
}

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
