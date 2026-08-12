<?php

if (!function_exists('storage_url')) {
    /**
     * Generate a proper URL for a file stored via the default filesystem disk.
     *
     * When FILESYSTEM_DISK=s3, this returns the S3/R2 public URL.
     * When FILESYSTEM_DISK=local, this returns the local /storage/... URL.
     *
     * @param  string|null  $path  The relative path stored in the database.
     * @return string|null
     */
    function storage_url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Already a full URL (e.g., external images)
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::url($path);
    }
}
