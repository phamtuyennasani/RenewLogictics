<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'db:restore
        {file : Path to the .sql.zip backup file}
        {--force : Run without confirmation}
        {--mysql= : Absolute path to mysql binary}';

    protected $description = 'Restore the configured MySQL database from a .sql.zip backup file';

    public function handle(): int
    {
        $this->ensureZipAvailable();

        $zipPath = (string) $this->argument('file');

        if (! File::exists($zipPath)) {
            throw new RuntimeException('Backup file not found: '.$zipPath);
        }

        if (! $this->option('force') && ! $this->confirm('This will restore into the configured MySQL database. Continue?')) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        $database = $this->databaseConfig();
        $mysql = $this->resolveBinary($this->option('mysql') ?: env('MYSQL_BINARY') ?: 'mysql');
        $extractDir = storage_path('app/backups/restore_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3)));

        File::ensureDirectoryExists($extractDir);

        try {
            $sqlPath = $this->extractSql($zipPath, $extractDir);

            $this->runMysqlTool(
                command: $this->mysqlCommand($database, $mysql),
                env: $this->mysqlPasswordEnv($database),
                stdinFile: $sqlPath,
            );
        } finally {
            if (File::isDirectory($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }

        $this->info('Database restored from: '.$zipPath);

        return self::SUCCESS;
    }

    protected function databaseConfig(): array
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('db:restore only supports the mysql connection.');
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

    protected function mysqlCommand(array $database, string $mysql): array
    {
        if (! $database['database'] || ! $database['username']) {
            throw new RuntimeException('Missing DB_DATABASE or DB_USERNAME in environment configuration.');
        }

        $command = [
            $mysql,
            '--binary-mode',
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

    protected function extractSql(string $zipPath, string $extractDir): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open zip file: '.$zipPath);
        }

        $sqlName = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (is_string($name) && str_ends_with(strtolower($name), '.sql')) {
                $sqlName = $name;
                break;
            }
        }

        if (! $sqlName) {
            $zip->close();
            throw new RuntimeException('No .sql file found inside backup zip.');
        }

        $zip->extractTo($extractDir, [$sqlName]);
        $zip->close();

        $sqlPath = $extractDir.DIRECTORY_SEPARATOR.$sqlName;

        if (! File::exists($sqlPath)) {
            throw new RuntimeException('Unable to extract SQL file from zip.');
        }

        return $sqlPath;
    }

    protected function mysqlPasswordEnv(array $database): array
    {
        return $database['password'] !== null ? ['MYSQL_PWD' => $database['password']] : [];
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

        throw new RuntimeException($binary.' was not found in PATH. Pass --mysql=/path/to/mysql or set MYSQL_BINARY in .env.');
    }

    protected function runMysqlTool(array $command, array $env = [], ?string $stdinFile = null): void
    {
        $descriptors = [
            0 => $stdinFile ? ['file', $stdinFile, 'r'] : ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, base_path(), array_merge($_ENV, $env));

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start process: '.$command[0]);
        }

        if (! $stdinFile) {
            fclose($pipes[0]);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) ?: trim($stdout) ?: $command[0].' failed with exit code '.$exitCode);
        }
    }
}
