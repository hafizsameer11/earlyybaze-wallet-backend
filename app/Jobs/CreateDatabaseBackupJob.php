<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $backup;

    /**
     * Create a new job instance.
     */
    public function __construct(DatabaseBackup $backup)
    {
        $this->backup = $backup;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Reload backup to get latest state
            $this->backup->refresh();
            $this->backup->status = 'processing';
            $this->backup->save();

            $filename = $this->backup->filename;
            $backupDir = 'backups';
            $filePath = $backupDir . '/' . $filename;
            $fullPath = storage_path('app/' . $filePath);

            // Create directory if it doesn't exist
            if (!file_exists(storage_path('app/' . $backupDir))) {
                mkdir(storage_path('app/' . $backupDir), 0755, true);
            }

            // Get database configuration
            $connection = config('database.default');
            $config = config("database.connections.{$connection}");
            
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            $host = $config['host'];
            $port = $config['port'] ?? 3306;

            // Create a temporary config file for mysqldump (more secure and reliable)
            $configFile = storage_path('app/backups/.my.cnf.' . uniqid());
            $configContent = "[client]\n";
            $configContent .= "host=" . $host . "\n";
            $configContent .= "port=" . $port . "\n";
            $configContent .= "user=" . $username . "\n";
            $configContent .= "password=" . $password . "\n";
            
            file_put_contents($configFile, $configContent);
            chmod($configFile, 0600); // Secure permissions (read/write for owner only)

            // Create mysqldump command using config file
            // --defaults-file must be first option
            // --single-transaction for InnoDB tables to ensure consistency (creates consistent snapshot)
            // --lock-tables=false to avoid locking issues
            // --routines to include stored procedures and functions
            // --triggers to include triggers
            // --events to include events
            // --add-drop-table to include DROP TABLE statements
            // --complete-insert to use complete INSERT statements
            // Note: Removed --quick flag as it may skip data for very large tables
            $command = sprintf(
                'mysqldump --defaults-file=%s %s --single-transaction --lock-tables=false --routines --triggers --events --add-drop-table --complete-insert 2>%s | gzip > %s',
                escapeshellarg($configFile),
                escapeshellarg($database),
                escapeshellarg($fullPath . '.error'),
                escapeshellarg($fullPath . '.gz')
            );

            Log::info("Starting database backup: {$filename}");
            Log::info("Command: " . str_replace($password, '***', $command));

            // Execute backup
            exec($command, $output, $returnVar);

            // Check for errors
            $errorFile = $fullPath . '.error';
            $errorOutput = '';
            if (file_exists($errorFile)) {
                $errorOutput = file_get_contents($errorFile);
                unlink($errorFile); // Clean up error file
            }

            // Clean up config file
            if (file_exists($configFile)) {
                unlink($configFile);
            }

            // Check if compressed file was created
            $compressedPath = $fullPath . '.gz';
            if (!file_exists($compressedPath)) {
                $error = $errorOutput ?: implode("\n", $output);
                Log::error("Backup file not created for {$filename}. Error: {$error}");
                throw new Exception('Backup file was not created. Error: ' . $error);
            }

            // Check file size
            $fileSize = filesize($compressedPath);
            if ($fileSize === 0) {
                $error = $errorOutput ?: implode("\n", $output);
                Log::error("Backup file is empty for {$filename}. Error: {$error}");
                throw new Exception('Backup file is empty. Error: ' . $error);
            }

            // Check for mysqldump errors in the output
            if ($returnVar !== 0 || !empty($errorOutput)) {
                $error = $errorOutput ?: implode("\n", $output);
                Log::error("Backup command failed for {$filename}: {$error}");
                
                // If file exists but has errors, check if it's a valid backup
                // Sometimes mysqldump returns non-zero but still creates a valid file
                if ($fileSize < 1000) { // Less than 1KB is definitely an error
                    unlink($compressedPath); // Clean up invalid file
                    throw new Exception('Backup failed: ' . $error);
                } else {
                    Log::warning("Backup completed with warnings for {$filename}: {$error}");
                }
            }

            // Update variables for compressed file
            $filename = $filename . '.gz';
            $filePath = $backupDir . '/' . $filename;
            $fullPath = $compressedPath;
            $compressed = true;

            // Verify backup contains data by checking for SQL statements
            // A valid backup should contain CREATE TABLE or INSERT statements
            $sample = '';
            try {
                $handle = gzopen($fullPath, 'r');
                if ($handle) {
                    $sample = gzread($handle, 1024); // Read first 1KB
                    gzclose($handle);
                }
            } catch (Exception $e) {
                Log::warning("Could not verify backup content: " . $e->getMessage());
            }

            if (!empty($sample) && (stripos($sample, 'CREATE TABLE') === false && stripos($sample, 'INSERT INTO') === false)) {
                Log::warning("Backup file may not contain valid SQL data. Sample: " . substr($sample, 0, 200));
            }

            // Update backup record
            $this->backup->filename = $filename;
            $this->backup->file_path = $filePath;
            $this->backup->size = filesize($fullPath);
            $this->backup->status = 'completed';
            $this->backup->save();

            Log::info("Database backup created successfully: {$filename} (Size: " . $this->backup->formatted_size . ", Compressed: " . ($compressed ? 'Yes' : 'No') . ")");

        } catch (Exception $e) {
            $this->backup->status = 'failed';
            $this->backup->save();
            
            Log::error("Database backup failed for {$this->backup->filename}: " . $e->getMessage());
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        if ($this->backup) {
            $this->backup->status = 'failed';
            $this->backup->save();
            
            Log::error("Database backup job failed for {$this->backup->filename}: " . $exception->getMessage());
        }
    }
}
