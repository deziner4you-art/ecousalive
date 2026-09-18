<?php
namespace App\Helpers;

class GoogleDriveHelper {

    private static $parentFolderId = '1d8Sonk24T7zQlyVHzIifcbtnW91c1jbE';
    private static $keyFilePath = __DIR__ . '/../../ecousalive-dd033933fe20.json';

    /**
     * Helper: Base64Url Encode
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Gets a fresh OAuth2 Access Token using the Service Account JSON without requiring any external libraries.
     */
    private static function logMsg($msg) {
        file_put_contents(__DIR__ . '/drive_debug.log', date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND);
    }

    private static function getAccessToken(): ?string {
        self::logMsg("Attempting to get access token");
        if (!file_exists(self::$keyFilePath)) {
            self::logMsg("Key missing at " . self::$keyFilePath);
            return null;
        }

        $json = file_get_contents(self::$keyFilePath);
        $credentials = json_decode($json, true);

        if (!$credentials || !isset($credentials['private_key']) || !isset($credentials['client_email'])) {
            error_log("Invalid Google JSON credentials.");
            return null;
        }

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claim = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => $credentials['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = self::base64url_encode($header);
        $base64UrlClaim = self::base64url_encode($claim);
        $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $credentials['private_key'], 'SHA256')) {
            self::logMsg("Failed to sign JWT. openssl_sign failed.");
            return null;
        }

        $jwt = $signatureInput . '.' . self::base64url_encode($signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $credentials['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            self::logMsg("Failed to get Google Token. HTTP: $httpCode, Response: " . $response);
            return null;
        }

        $res = json_decode($response, true);
        if (isset($res['access_token'])) {
            self::logMsg("Successfully obtained access token.");
            return $res['access_token'];
        }
        self::logMsg("Access token missing in response.");
        return null;
    }

    /**
     * Executes a curl request to Google APIs.
     */
    private static function apiRequest(string $url, string $method = 'GET', array $headers = [], $body = null, bool $isUpload = false) {
        $token = self::getAccessToken();
        if (!$token) return null;

        $headers[] = 'Authorization: Bearer ' . $token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::logMsg("apiRequest: $method $url - HTTP $httpCode");

        if ($httpCode >= 400) {
            self::logMsg("Google API Error [$httpCode]: " . $response);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Finds an existing folder by name inside the parent folder, or creates it if not found.
     */
    public static function getOrCreateProductFolder(string $folderName): ?array {
        $parentId = self::$parentFolderId;
        $escapedName = str_replace("'", "\'", $folderName);
        $query = "name = '{$escapedName}' and mimeType = 'application/vnd.google-apps.folder' and '{$parentId}' in parents and trashed = false";
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,webViewLink)&supportsAllDrives=true&includeItemsFromAllDrives=true';

        $searchResult = self::apiRequest($url);
        
        if ($searchResult && isset($searchResult['files']) && count($searchResult['files']) > 0) {
            return [
                'id' => $searchResult['files'][0]['id'],
                'link' => $searchResult['files'][0]['webViewLink']
            ];
        }

        // Create folder
        $createUrl = 'https://www.googleapis.com/drive/v3/files?fields=id,webViewLink&supportsAllDrives=true';
        $body = json_encode([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ]);
        $headers = ['Content-Type: application/json'];

        $createResult = self::apiRequest($createUrl, 'POST', $headers, $body);

        if ($createResult && isset($createResult['id'])) {
            return [
                'id' => $createResult['id'],
                'link' => $createResult['webViewLink']
            ];
        }

        return null;
    }

    /**
     * Uploads a file to a specific Google Drive folder using Multipart upload.
     */
    public static function uploadFile(string $folderId, string $filePath, string $originalName, string $mimeType): bool {
        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
        
        $metadata = json_encode([
            'name' => $originalName,
            'parents' => [$folderId]
        ]);

        $boundary = '-------314159265358979323846';
        $body = "--{$boundary}\r\n"
              . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
              . "{$metadata}\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: {$mimeType}\r\n\r\n"
              . file_get_contents($filePath) . "\r\n"
              . "--{$boundary}--";

        $headers = [
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ];

        $uploadResult = self::apiRequest($url, 'POST', $headers, $body, true);
        
        return $uploadResult && isset($uploadResult['id']);
    }

    /**
     * Uploads or Replaces a file in a specific Google Drive folder.
     */
    public static function uploadOrReplaceFile(string $folderId, string $filePath, string $originalName, string $mimeType): bool {
        // 1. Check if file exists
        $escapedName = str_replace("'", "\'", $originalName);
        $query = "name = '{$escapedName}' and '{$folderId}' in parents and trashed = false";
        $searchUrl = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id)&supportsAllDrives=true&includeItemsFromAllDrives=true';
        $searchResult = self::apiRequest($searchUrl);
        
        $fileId = null;
        if ($searchResult && !empty($searchResult['files'])) {
            $fileId = $searchResult['files'][0]['id'];
        }

        $boundary = '-------314159265358979323846';
        
        if ($fileId) {
            // Update existing
            $url = "https://www.googleapis.com/upload/drive/v3/files/{$fileId}?uploadType=multipart&supportsAllDrives=true";
            $method = 'PATCH';
            $metadata = json_encode(['name' => $originalName]);
        } else {
            // Create new
            $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true';
            $method = 'POST';
            $metadata = json_encode(['name' => $originalName, 'parents' => [$folderId]]);
        }

        $body = "--{$boundary}\r\n"
              . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
              . "{$metadata}\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: {$mimeType}\r\n\r\n"
              . file_get_contents($filePath) . "\r\n"
              . "--{$boundary}--";

        $headers = [
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ];

        $uploadResult = self::apiRequest($url, $method, $headers, $body, true);
        
        return $uploadResult && isset($uploadResult['id']);
    }

    /**
     * Fetch list of files from a specific Google Drive folder.
     */
    public static function getFilesInFolder(string $folderId): ?array {
        $query = "'{$folderId}' in parents and trashed = false";
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,webViewLink)&supportsAllDrives=true&includeItemsFromAllDrives=true';
        $result = self::apiRequest($url);
        
        if ($result && isset($result['files'])) {
            return $result['files'];
        }
        return [];
    }
}
