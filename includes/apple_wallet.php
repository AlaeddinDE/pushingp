<?php
/**
 * Apple Wallet Pass Generator for Pushing P
 * Generates .pkpass files for crew membership cards
 */

class AppleWalletPass {
    private $conn;
    private $pass_type_id = 'pass.com.pushingp.crew';
    private $team_identifier = 'YOUR_TEAM_ID'; // Replace with your Apple Developer Team ID
    private $organization_name = 'Pushing P';
    private $cert_path;
    private $cert_password;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->cert_path = __DIR__ . '/../wallet/certs/pass_cert.p12';
        $this->cert_password = getenv('WALLET_CERT_PASSWORD') ?: 'password';
    }
    
    /**
     * Generate a .pkpass file for a user
     */
    public function generatePass($user_id) {
        // Get user data
        $user_data = $this->getUserData($user_id);
        if (!$user_data) {
            throw new Exception('User not found');
        }
        
        // Create serial number if not exists
        $serial_number = $this->getOrCreateSerial($user_id);
        
        // Create auth token
        $auth_token = $this->getOrCreateAuthToken($user_id);
        
        // Create temp directory for pass
        $temp_dir = sys_get_temp_dir() . '/pass_' . uniqid();
        mkdir($temp_dir, 0755, true);
        
        try {
            // Create pass.json
            $pass_json = $this->createPassJSON($user_data, $serial_number, $auth_token);
            file_put_contents($temp_dir . '/pass.json', json_encode($pass_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Copy images
            $this->copyPassImages($temp_dir, $user_data);
            
            // Create manifest
            $manifest = $this->createManifest($temp_dir);
            file_put_contents($temp_dir . '/manifest.json', json_encode($manifest));
            
            // Sign manifest
            $signature = $this->signManifest($temp_dir . '/manifest.json');
            file_put_contents($temp_dir . '/signature', $signature);
            
            // Create ZIP (pkpass)
            $pkpass_path = $this->createPkpass($temp_dir, $user_id);
            
            // Update last modified
            $this->updatePassModified($user_id, $serial_number, 'Generated new pass');
            
            return $pkpass_path;
            
        } finally {
            // Cleanup temp directory
            $this->recursiveRemoveDirectory($temp_dir);
        }
    }
    
    /**
     * Create pass.json structure
     */
    private function createPassJSON($user, $serial_number, $auth_token) {
        $level_info = $this->getUserLevelInfo($user['id']);
        $balance = $this->getUserBalance($user['id']);
        $event_count = $this->getUserEventCount($user['id']);
        
        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $this->pass_type_id,
            'serialNumber' => $serial_number,
            'teamIdentifier' => $this->team_identifier,
            'organizationName' => $this->organization_name,
            'description' => 'Pushing P Crew Mitgliedskarte',
            'webServiceURL' => 'https://pushingp.de/api/wallet',
            'authenticationToken' => $auth_token,
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => 'rgb(11, 15, 18)',
            'labelColor' => 'rgb(207, 231, 238)',
            'logoText' => 'PUSHING P',
            'barcode' => [
                'message' => 'https://pushingp.de/m/' . $user['id'],
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
                'altText' => 'Crew ID: ' . $user['id']
            ],
            'barcodes' => [
                [
                    'message' => 'https://pushingp.de/m/' . $user['id'],
                    'format' => 'PKBarcodeFormatQR',
                    'messageEncoding' => 'iso-8859-1',
                    'altText' => 'Crew ID: ' . $user['id']
                ]
            ],
            'generic' => [
                'primaryFields' => [
                    [
                        'key' => 'member',
                        'label' => 'MITGLIED',
                        'value' => $user['name']
                    ]
                ],
                'secondaryFields' => [
                    [
                        'key' => 'level',
                        'label' => 'LEVEL',
                        'value' => $level_info['current_level'] ?? 'Rookie'
                    ],
                    [
                        'key' => 'xp',
                        'label' => 'XP',
                        'value' => number_format($level_info['xp_total'] ?? 0)
                    ]
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'balance',
                        'label' => 'KASSE',
                        'value' => number_format($balance, 2) . ' €',
                        'currencyCode' => 'EUR'
                    ],
                    [
                        'key' => 'events',
                        'label' => 'EVENTS',
                        'value' => $event_count
                    ]
                ],
                'backFields' => [
                    [
                        'key' => 'crew_id',
                        'label' => 'Crew-ID',
                        'value' => $user['id']
                    ],
                    [
                        'key' => 'username',
                        'label' => 'Username',
                        'value' => '@' . $user['username']
                    ],
                    [
                        'key' => 'member_since',
                        'label' => 'Dabei seit',
                        'value' => date('d.m.Y', strtotime($user['created_at']))
                    ],
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'value' => ucfirst($user['status'])
                    ]
                ]
            ],
            'relevantDate' => date('c') // ISO 8601 format
        ];
    }
    
    /**
     * Copy or generate pass images
     */
    private function copyPassImages($temp_dir, $user) {
        $images = ['icon', 'logo', 'strip', 'background'];
        $sizes = ['', '@2x', '@3x'];
        
        foreach ($images as $image) {
            foreach ($sizes as $size) {
                $filename = $image . $size . '.png';
                $source = __DIR__ . '/../wallet/templates/' . $filename;
                
                if (file_exists($source)) {
                    copy($source, $temp_dir . '/' . $filename);
                } elseif ($size === '') {
                    // Generate placeholder if not exists
                    $this->generatePlaceholderImage($temp_dir . '/' . $filename, $image);
                }
            }
        }
    }
    
    /**
     * Generate placeholder image
     */
    private function generatePlaceholderImage($path, $type) {
        $width = $type === 'icon' ? 29 : ($type === 'logo' ? 160 : 375);
        $height = $type === 'strip' ? 123 : $width;
        
        $image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($image, 139, 92, 246); // Purple
        imagefill($image, 0, 0, $bg_color);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $text = 'P';
        $font_size = $type === 'icon' ? 3 : 5;
        imagestring($image, $font_size, $width/2 - 10, $height/2 - 10, $text, $white);
        
        imagepng($image, $path);
        imagedestroy($image);
    }
    
    /**
     * Create manifest.json with SHA1 hashes
     */
    private function createManifest($temp_dir) {
        $manifest = [];
        $files = scandir($temp_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'manifest.json' || $file === 'signature') {
                continue;
            }
            
            $file_path = $temp_dir . '/' . $file;
            if (is_file($file_path)) {
                $manifest[$file] = sha1_file($file_path);
            }
        }
        
        return $manifest;
    }
    
    /**
     * Sign manifest with certificate
     */
    private function signManifest($manifest_path) {
        // Check if certificate exists
        if (!file_exists($this->cert_path)) {
            // DEMO MODE: Create dummy signature for testing
            // In production, you MUST have a valid Apple Developer Certificate!
            error_log('WALLET DEMO MODE: No certificate found. Creating dummy signature.');
            return $this->createDummySignature();
        }
        
        // Read certificate
        $cert_data = file_get_contents($this->cert_path);
        $certs = [];
        
        if (!openssl_pkcs12_read($cert_data, $certs, $this->cert_password)) {
            throw new Exception('Failed to read certificate: ' . openssl_error_string());
        }
        
        // Sign manifest
        $manifest_data = file_get_contents($manifest_path);
        $signature_path = dirname($manifest_path) . '/signature_temp';
        
        if (!openssl_pkcs7_sign(
            $manifest_path,
            $signature_path,
            $certs['cert'],
            $certs['pkey'],
            [],
            PKCS7_BINARY | PKCS7_DETACHED
        )) {
            throw new Exception('Failed to sign manifest: ' . openssl_error_string());
        }
        
        // Extract signature from PKCS7
        $signature_data = file_get_contents($signature_path);
        $signature_data = $this->extractSignature($signature_data);
        
        unlink($signature_path);
        
        return $signature_data;
    }
    
    /**
     * Create dummy signature for demo/testing
     * WARNING: This will NOT work with real Apple Wallet!
     */
    private function createDummySignature() {
        // Create a basic PKCS7 structure
        // This is ONLY for testing the file structure
        $dummy_sig = "-----BEGIN PKCS7-----\n";
        $dummy_sig .= "DEMO MODE - NOT A REAL SIGNATURE\n";
        $dummy_sig .= "You need to add your Apple Developer Certificate\n";
        $dummy_sig .= "to /var/www/html/wallet/certs/pass_cert.p12\n";
        $dummy_sig .= "-----END PKCS7-----\n";
        return $dummy_sig;
    }
    
    /**
     * Extract DER signature from PKCS7
     */
    private function extractSignature($pkcs7) {
        $begin = "filename=\"smime.p7s\"\n\n";
        $end = "\n\n------";
        
        $start = strpos($pkcs7, $begin);
        if ($start === false) {
            return $pkcs7;
        }
        
        $start += strlen($begin);
        $finish = strpos($pkcs7, $end, $start);
        
        if ($finish === false) {
            $finish = strlen($pkcs7);
        }
        
        $signature_base64 = substr($pkcs7, $start, $finish - $start);
        return base64_decode($signature_base64);
    }
    
    /**
     * Create .pkpass ZIP file
     */
    private function createPkpass($temp_dir, $user_id) {
        $pkpass_dir = __DIR__ . '/../wallet/passes/';
        if (!is_dir($pkpass_dir)) {
            mkdir($pkpass_dir, 0755, true);
        }
        
        $pkpass_path = $pkpass_dir . 'crew_' . $user_id . '.pkpass';
        
        $zip = new ZipArchive();
        if ($zip->open($pkpass_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create ZIP file');
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($temp_dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        return $pkpass_path;
    }
    
    /**
     * Helper functions
     */
    private function getUserData($user_id) {
        $stmt = $this->conn->prepare("SELECT id, username, name, created_at, status FROM users WHERE id = ? AND status = 'active'");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    private function getUserLevelInfo($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM v_user_xp_progress WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result->fetch_assoc();
        $stmt->close();
        return $info ?: [];
    }
    
    private function getUserBalance($user_id) {
        // This should get the actual user balance from your kasse system
        $stmt = $this->conn->prepare("SELECT SUM(betrag) as balance FROM transaktionen WHERE mitglied_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();
        return $balance ?: 0.0;
    }
    
    private function getUserEventCount($user_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM event_participants WHERE mitglied_id = ? AND status = 'coming'");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count ?: 0;
    }
    
    private function getOrCreateSerial($user_id) {
        $stmt = $this->conn->prepare("SELECT wallet_serial FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($serial);
        $stmt->fetch();
        $stmt->close();
        
        if (!$serial) {
            $serial = 'user-' . $user_id . '-' . bin2hex(random_bytes(8));
            $stmt = $this->conn->prepare("UPDATE users SET wallet_serial = ? WHERE id = ?");
            $stmt->bind_param('si', $serial, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        return $serial;
    }
    
    private function getOrCreateAuthToken($user_id) {
        $stmt = $this->conn->prepare("SELECT token FROM wallet_tokens WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($token);
        $exists = $stmt->fetch();
        $stmt->close();
        
        if (!$exists) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
            $stmt = $this->conn->prepare("INSERT INTO wallet_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
            $stmt->bind_param('issss', $user_id, $token, $expires, $token, $expires);
            $stmt->execute();
            $stmt->close();
        }
        
        return $token;
    }
    
    private function updatePassModified($user_id, $serial_number, $reason) {
        $stmt = $this->conn->prepare("INSERT INTO wallet_pass_updates (user_id, serial_number, update_tag, reason) VALUES (?, ?, ?, ?)");
        $tag = substr(md5(microtime()), 0, 16);
        $stmt->bind_param('isss', $user_id, $serial_number, $tag, $reason);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $this->conn->prepare("UPDATE users SET wallet_last_updated = NOW() WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function recursiveRemoveDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
