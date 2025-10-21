<?php

// Storage abstraction: local filesystem or Supabase Storage

function storage_driver(): string {
    $drv = $_ENV['STORAGE_DRIVER'] ?? getenv('STORAGE_DRIVER') ?? 'local';
    return strtolower($drv) === 'supabase' ? 'supabase' : 'local';
}

function storage_local_root(): string {
    // Relative to web root
    return __DIR__ . '/../images/uploads';
}

function storage_public_url(string $relativePath): string {
    $relativePath = ltrim($relativePath, '/');
    if (storage_driver() === 'supabase') {
        $base = rtrim($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?? '', '/');
        $bucket = $_ENV['SUPABASE_STORAGE_BUCKET'] ?? getenv('SUPABASE_STORAGE_BUCKET') ?? 'logos';
        if ($base === '') {
            // Fallback: return a relative path so UI doesn't explode
            return '/images/uploads/' . $relativePath;
        }
        return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($relativePath));
    }
    // local
    return '/images/uploads/' . $relativePath;
}

function storage_put_file(string $relativePath, string $localFilePath, ?string $contentType = null): bool {
    $relativePath = ltrim($relativePath, '/');
    if (storage_driver() === 'supabase') {
        $base = rtrim($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?? '', '/');
        $key  = $_ENV['SUPABASE_SERVICE_KEY'] ?? getenv('SUPABASE_SERVICE_KEY') ?? '';
        $bucket = $_ENV['SUPABASE_STORAGE_BUCKET'] ?? getenv('SUPABASE_STORAGE_BUCKET') ?? 'logos';
        if ($base === '' || $key === '') { return false; }
        $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($relativePath));
        $data = @file_get_contents($localFilePath);
        if ($data === false) { return false; }
        return storage_put_data($relativePath, $data, $contentType);
    }
    // local: write into images/uploads
    $full = rtrim(storage_local_root(), '/\\') . '/' . $relativePath;
    $dir = dirname($full);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    return @rename($localFilePath, $full) || @copy($localFilePath, $full);
}

function storage_put_data(string $relativePath, string $data, ?string $contentType = null): bool {
    $relativePath = ltrim($relativePath, '/');
    if (storage_driver() === 'supabase') {
        $base = rtrim($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?? '', '/');
        $key  = $_ENV['SUPABASE_SERVICE_KEY'] ?? getenv('SUPABASE_SERVICE_KEY') ?? '';
        $bucket = $_ENV['SUPABASE_STORAGE_BUCKET'] ?? getenv('SUPABASE_STORAGE_BUCKET') ?? 'logos';
        if ($base === '' || $key === '') { return false; }
        $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($relativePath));

        $headers = [
            'Authorization: Bearer ' . $key,
            'x-upsert: true',
        ];
        if ($contentType) { $headers[] = 'Content-Type: ' . $contentType; }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false || $code >= 300) {
            error_log('[storage] supabase upload failed: HTTP ' . $code . ' body=' . substr((string)$resp, 0, 200));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return true;
    }
    // local
    $full = rtrim(storage_local_root(), '/\\') . '/' . $relativePath;
    $dir = dirname($full);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    return @file_put_contents($full, $data) !== false;
}

function storage_delete(string $relativePath): bool {
    $relativePath = ltrim($relativePath, '/');
    if (storage_driver() === 'supabase') {
        $base = rtrim($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?? '', '/');
        $key  = $_ENV['SUPABASE_SERVICE_KEY'] ?? getenv('SUPABASE_SERVICE_KEY') ?? '';
        $bucket = $_ENV['SUPABASE_STORAGE_BUCKET'] ?? getenv('SUPABASE_STORAGE_BUCKET') ?? 'logos';
        if ($base === '' || $key === '') { return false; }
        $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($relativePath));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code < 300;
    }
    $full = rtrim(storage_local_root(), '/\\') . '/' . $relativePath;
    return @unlink($full);
}

?>
