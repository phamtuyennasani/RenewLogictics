<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
        {--path= : Folder to store the .sql.zip file}
        {--filename= : Backup filename without extension}
        {--mysqldump= : Absolute path to mysqldump binary}';

    protected $description = 'Backup the configured MySQL database to a .sql.zip file';

    public function handle(): int
    {
        $this->ensureZipAvailable();

        $database = $this->databaseConfig();
        $mysqldump = $this->resolveBinary($this->option('mysqldump') ?: env('MYSQLDUMP_BINARY') ?: 'mysqldump');
        $backupDir = $this->option('path') ?: storage_path('app/backups/database');
        $filename = $this->option('filename') ?: $database['database'].'_'.now()->format('Ymd_His');
        $sqlPath = $backupDir.DIRECTORY_SEPARATOR.$filename.'.sql';
        $zipPath = $backupDir.DIRECTORY_SEPARATOR.$filename.'.sql.zip';

        File::ensureDirectoryExists($backupDir);

        try {
            $this->runMysqlTool(
                command: $this->mysqldumpCommand($database, $mysqldump),
                env: $this->mysqlPasswordEnv($database),
                stdoutFile: $sqlPath,
            );

            $this->zipSqlFile($sqlPath, $zipPath);
        } finally {
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }
        }

        $this->info('Database backup created: '.$zipPath);

        return self::SUCCESS;
    }

    protected function databaseConfig(): array
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('db:backup only supports the mysql connection.');
        }

        $config = config('database.connections.mysql');

        return [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? 3306),
            'socket' => $config['unix_socket'] ?? null,
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
        ];
    }

    protected function mysqldumpCommand(array $database, string $mysqldump): array
    {
        if (! $database['database'] || ! $database['username']) {
            throw new RuntimeException('Missing DB_DATABASE or DB_USERNAME in environment configuration.');
        }

        $command = [
            $mysqldump,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '-u',
            $database['username'],
        ];

        if ($database['socket']) {
            $command[] = '--socket='.$database['socket'];
        } else {
            array_push($command, '-h', $database['host'], '-P', $database['port']);
        }

        $command[] = $database['database'];

        return $command;
    }

    protected function mysqlPasswordEnv(array $database): array
    {
        return $database['password'] !== null ? ['MYSQL_PWD' => $database['password']] : [];
    }

    protected function zipSqlFile(string $sqlPath, string $zipPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip file: '.$zipPath);
        }

        $zip->addFile($sqlPath, basename($sqlPath));
        $zip->close();
    }

    protected function ensureZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required.');
        }
    }

    protected function resolveBinary(string $binary): string
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            if (is_executable($binary)) {
                return $binary;
            }

            throw new RuntimeException('Binary is not executable: '.$binary);
        }

        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $path) {
            $candidate = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary;

            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException($binary.' was not found in PATH. Pass --mysqldump=/path/to/mysqldump or set MYSQLDUMP_BINARY in .env.');
    }

    protected function runMysqlTool(array $command, array $env = [], ?string $stdoutFile = null): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => $stdoutFile ? ['file', $stdoutFile, 'w'] : ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, base_path(), $this->processEnvironment($env));

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start process: '.$command[0]);
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (! $stdoutFile && isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) ?: $command[0].' failed with exit code '.$exitCode);
        }
    }

    protected function processEnvironment(array $env = []): array
    {
        $environment = array_replace(getenv() ?: [], $_ENV, $env);

        return array_filter($environment, static fn ($value): bool => is_scalar($value));
    }
}
