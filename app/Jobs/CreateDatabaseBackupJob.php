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

            // Create mysqldump command
            // Use --single-transaction for InnoDB tables to ensure consistency
            // Use --quick for faster dumps
            // Use --lock-tables=false to avoid locking issues
            $command = sprintf(
                'mysqldump -h %s -P %d -u %s -p%s %s --single-transaction --quick --lock-tables=false > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($fullPath)
            );

            // Execute backup
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $error = implode("\n", $output);
                Log::error("Backup failed for {$filename}: {$error}");
                throw new Exception('Backup failed: ' . $error);
            }

            // Check if file was created and has content
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new Exception('Backup file was not created or is empty');
            }

            // Compress backup using gzip (optional but recommended)
            $compressedPath = $fullPath . '.gz';
            $compressed = false;
            
            try {
                // Try to compress the backup
                $source = fopen($fullPath, 'rb');
                $dest = gzopen($compressedPath, 'wb9'); // wb9 = highest compression
                
                if ($source && $dest) {
                    while (!feof($source)) {
                        gzwrite($dest, fread($source, 8192));
                    }
                    fclose($source);
                    gzclose($dest);
                    
                    if (file_exists($compressedPath) && filesize($compressedPath) > 0) {
                        // Remove original file
                        unlink($fullPath);
                        $filename = $filename . '.gz';
                        $filePath = $backupDir . '/' . $filename;
                        $fullPath = $compressedPath;
                        $compressed = true;
                    }
                }
            } catch (Exception $e) {
                Log::warning("Failed to compress backup {$filename}: " . $e->getMessage());
                // Continue with uncompressed backup
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
