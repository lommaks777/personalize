<?php
// user_profiles.php - Управление профилями пользователей
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/utils.php';

$profilesFile = __DIR__ . '/store/user_profiles.json';

function loadUserProfiles() {
    global $profilesFile;
    if (!file_exists($profilesFile)) {
        return [];
    }
    $data = file_get_contents($profilesFile);
    return json_decode($data, true) ?: [];
}

function saveUserProfiles($profiles) {
    global $profilesFile;
    file_put_contents($profilesFile, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadUserProfile($userId) {
    $profiles = loadUserProfiles();
    return $profiles[$userId] ?? null;
}

function createUserProfile($userId, $profileData) {
    $profiles = loadUserProfiles();
    $profiles[$userId] = $profileData;
    saveUserProfiles($profiles);
    return $profileData;
}
?>


