<?php
/**
 * Google OAuth Helper Class
 * Handles Google OAuth authentication flow
 */
class GoogleOAuth {
    private $client;
    private $opt;
    
    public function __construct($opt) {
        $this->opt = $opt;
        
        // Check if Google OAuth is enabled and configured
        if (!$this->isEnabled()) {
            throw new Exception("Google OAuth is not enabled or properly configured");
        }
        
        // Initialize Google Client
        $this->client = new Google_Client();
        $this->client->setClientId($opt['google_client_id']);
        $this->client->setClientSecret($opt['google_client_secret']);
        $this->client->setRedirectUri($opt['google_redirect_uri']);
        $this->client->addScope('email');
        $this->client->addScope('profile');
    }
    
    /**
     * Check if Google OAuth is enabled and properly configured
     */
    public function isEnabled() {
        return $this->opt['google_oauth_enabled'] && 
               !empty($this->opt['google_client_id']) && 
               !empty($this->opt['google_client_secret']) && 
               !empty($this->opt['google_redirect_uri']);
    }
    
    /**
     * Get the Google OAuth authorization URL
     */
    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }
    
    /**
     * Handle OAuth callback and get user information
     */
    public function handleCallback($code) {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new Exception('OAuth error: ' . $token['error']);
        }
        
        $this->client->setAccessToken($token);
        
        // Get user information
        $oauth2 = new Google_Service_Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();
        
        return [
            'google_id' => $userInfo->getId(),
            'email' => $userInfo->getEmail(),
            'name' => $userInfo->getName(),
            'picture' => $userInfo->getPicture(),
            'verified_email' => $userInfo->getVerifiedEmail()
        ];
    }
    
    /**
     * Find or create user based on Google account information
     */
    public function findOrCreateUser($userInfo, $dbh, $opt) {
        // First, try to find existing user by Google ID
        $stmt = $dbh->prepare("SELECT * FROM {$opt['table_prefix']}users WHERE google_id = ?");
        $stmt->bindParam(1, $userInfo['google_id'], PDO::PARAM_STR);
        $stmt->execute();
        
        if ($user = $stmt->fetch()) {
            return $user;
        }
        
        // Try to find existing user by email
        $stmt = $dbh->prepare("SELECT * FROM {$opt['table_prefix']}users WHERE email = ? AND approved = 1");
        $stmt->bindParam(1, $userInfo['email'], PDO::PARAM_STR);
        $stmt->execute();
        
        if ($user = $stmt->fetch()) {
            // Link the Google account to existing user
            $stmt = $dbh->prepare("UPDATE {$opt['table_prefix']}users SET google_id = ? WHERE userid = ?");
            $stmt->bindParam(1, $userInfo['google_id'], PDO::PARAM_STR);
            $stmt->bindParam(2, $user['userid'], PDO::PARAM_INT);
            $stmt->execute();
            
            $user['google_id'] = $userInfo['google_id'];
            return $user;
        }
        
        // Create new user if auto-approval is enabled for OAuth users
        if (!$opt['newuser_requires_approval']) {
            return $this->createNewUser($userInfo, $dbh, $opt);
        }
        
        return null; // User not found and auto-creation not allowed
    }
    
    /**
     * Create a new user from Google account information
     */
    private function createNewUser($userInfo, $dbh, $opt) {
        // Create default family for new user
        $familyName = $userInfo['name'] . "'s Family";
        $stmt = $dbh->prepare("INSERT INTO {$opt['table_prefix']}families(familyname) VALUES(?)");
        $stmt->bindParam(1, $familyName, PDO::PARAM_STR);
        $stmt->execute();
        
        $familyId = $dbh->lastInsertId();
        
        // Generate random username from email
        $username = $this->generateUsername($userInfo['email'], $dbh, $opt);
        
        // Create user account
        $stmt = $dbh->prepare("INSERT INTO {$opt['table_prefix']}users(username, fullname, email, google_id, approved, admin, initialfamilyid, password) VALUES(?, ?, ?, ?, 1, 0, ?, '')");
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->bindParam(2, $userInfo['name'], PDO::PARAM_STR);
        $stmt->bindParam(3, $userInfo['email'], PDO::PARAM_STR);
        $stmt->bindParam(4, $userInfo['google_id'], PDO::PARAM_STR);
        $stmt->bindParam(5, $familyId, PDO::PARAM_INT);
        $stmt->execute();
        
        $userId = $dbh->lastInsertId();
        
        return [
            'userid' => $userId,
            'username' => $username,
            'fullname' => $userInfo['name'],
            'email' => $userInfo['email'],
            'google_id' => $userInfo['google_id'],
            'admin' => 0,
            'approved' => 1
        ];
    }
    
    /**
     * Generate a unique username from email
     */
    private function generateUsername($email, $dbh, $opt) {
        $baseUsername = explode('@', $email)[0];
        $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $baseUsername);
        $baseUsername = substr($baseUsername, 0, 15); // Limit length
        
        $username = $baseUsername;
        $counter = 1;
        
        // Ensure username is unique
        while (true) {
            $stmt = $dbh->prepare("SELECT userid FROM {$opt['table_prefix']}users WHERE username = ?");
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                break; // Username is available
            }
            
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        return $username;
    }
}
?>