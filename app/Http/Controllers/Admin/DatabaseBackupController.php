<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\CreateDatabaseBackupJob;
use App\Models\DatabaseBackup;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DatabaseBackupController extends Controller
{
    /**
     * Create a new database backup
     */
    public function create(Request $request)
    {
        try {
            $backup = DatabaseBackup::create([
                'filename' => 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql',
                'file_path' => '', // Will be set after backup is created
                'status' => 'processing',
                'created_by' => auth()->id(),
            ]);

            // Dispatch backup job to queue (or run synchronously if queue is not configured)
            CreateDatabaseBackupJob::dispatch($backup);

            return ResponseHelper::success([
                'id' => $backup->id,
                'filename' => $backup->filename,
                'status' => $backup->status,
                'created_at' => $backup->created_at->toISOString(),
            ], 'Backup creation initiated', 202);

        } catch (Exception $e) {
            Log::error('Failed to create backup: ' . $e->getMessage());
            return ResponseHelper::error('Failed to create backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all backups with pagination
     */
    public function getAll(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 20);
            $sort = $request->query('sort', 'created_at');
            $order = $request->query('order', 'desc');

            // Validate sort field
            $allowedSorts = ['created_at', 'filename', 'size', 'status'];
            if (!in_array($sort, $allowedSorts)) {
                $sort = 'created_at';
            }

            // Validate order
            $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

            $backups = DatabaseBackup::orderBy($sort, $order)
                ->paginate($perPage);

            $data = $backups->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'filename' => $backup->filename,
                    'size' => $backup->size,
                    'formatted_size' => $backup->formatted_size,
                    'created_at' => $backup->created_at->toISOString(),
                    'status' => $backup->status,
                    'created_by' => $backup->creator ? [
                        'id' => $backup->creator->id,
                        'name' => $backup->creator->name ?? $backup->creator->email,
                    ] : null,
                ];
            });

            return ResponseHelper::success([
                'data' => $data,
                'meta' => [
                    'total' => $backups->total(),
                    'page' => $backups->currentPage(),
                    'per_page' => $backups->perPage(),
                    'total_pages' => $backups->lastPage(),
                ],
            ], 'Backups fetched successfully', 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch backups: ' . $e->getMessage());
            return ResponseHelper::error('Failed to fetch backups: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download a backup file
     */
    public function download($id)
    {
        try {
            $backup = DatabaseBackup::findOrFail($id);

            if ($backup->status !== 'completed') {
                return ResponseHelper::error('Backup is not ready for download. Current status: ' . $backup->status, 400);
            }

            if (empty($backup->file_path)) {
                return ResponseHelper::error('Backup file path is not set', 404);
            }

            // Check if file exists in storage
            if (!Storage::exists($backup->file_path)) {
                // Try absolute path as fallback
                $fullPath = storage_path('app/' . $backup->file_path);
                if (!file_exists($fullPath)) {
                    return ResponseHelper::error('Backup file not found', 404);
                }
                // Return file download
                return response()->download($fullPath, $backup->filename);
            }

            // Return file download from storage
            return Storage::download($backup->file_path, $backup->filename);

        } catch (Exception $e) {
            Log::error('Failed to download backup: ' . $e->getMessage());
            return ResponseHelper::error('Failed to download backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a backup
     */
    public function delete($id)
    {
        try {
            $backup = DatabaseBackup::findOrFail($id);

            // Delete file from storage
            if (!empty($backup->file_path)) {
                try {
                    if (Storage::exists($backup->file_path)) {
                        Storage::delete($backup->file_path);
                    } else {
                        // Try absolute path as fallback
                        $fullPath = storage_path('app/' . $backup->file_path);
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to delete backup file: ' . $e->getMessage());
                }
            }

            // Delete record
            $backup->delete();

            return ResponseHelper::success(null, 'Backup deleted successfully', 200);

        } catch (Exception $e) {
            Log::error('Failed to delete backup: ' . $e->getMessage());
            return ResponseHelper::error('Failed to delete backup: ' . $e->getMessage(), 500);
        }
    }
}
