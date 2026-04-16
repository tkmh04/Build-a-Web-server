<?php
declare(strict_types=1);

function parseUserAgentDetails(string $userAgent): array
{
    $ua = strtolower($userAgent);

    $deviceType = 'Desktop';
    if (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false || strpos($ua, 'crawler') !== false) {
        $deviceType = 'Bot';
    } elseif (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
        $deviceType = 'Tablet';
    } elseif (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
        $deviceType = 'Mobile';
    }

    $deviceName = 'Unknown';
    if (preg_match('/iphone/i', $userAgent)) {
        $deviceName = 'iPhone';
    } elseif (preg_match('/ipad/i', $userAgent)) {
        $deviceName = 'iPad';
    } elseif (preg_match('/android[^;]*;\s*([^;\)]+?)(?:\s+build|\))/i', $userAgent, $match)) {
        $deviceName = trim($match[1]);
    } elseif (preg_match('/windows/i', $userAgent)) {
        $deviceName = 'PC';
    } elseif (preg_match('/linux/i', $userAgent)) {
        $deviceName = 'Linux PC';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $deviceName = 'Mac';
    }

    $osName = 'Unknown';
    $osVersion = '-';
    if (preg_match('/windows nt\s*10\.0/i', $userAgent)) {
        $osName = 'Windows';
        $osVersion = '10/11';
    } elseif (preg_match('/windows nt\s*6\.3/i', $userAgent)) {
        $osName = 'Windows';
        $osVersion = '8.1';
    } elseif (preg_match('/windows nt\s*6\.2/i', $userAgent)) {
        $osName = 'Windows';
        $osVersion = '8';
    } elseif (preg_match('/windows nt\s*6\.1/i', $userAgent)) {
        $osName = 'Windows';
        $osVersion = '7';
    } elseif (preg_match('/debian(?:\s*\/)?\s*([0-9\.]+)?/i', $userAgent, $match)) {
        $osName = 'Debian Linux';
        $osVersion = $match[1] !== '' ? (string) $match[1] : '-';
    } elseif (preg_match('/ubuntu(?:\s*\/)?\s*([0-9\.]+)?/i', $userAgent, $match)) {
        $osName = 'Ubuntu Linux';
        $osVersion = $match[1] !== '' ? (string) $match[1] : '-';
    } elseif (preg_match('/kali(?:\s*\/)?\s*([0-9\.]+)?/i', $userAgent, $match)) {
        $osName = 'Kali Linux';
        $osVersion = $match[1] !== '' ? (string) $match[1] : '-';
    } elseif (preg_match('/android\s*([0-9\.]+)/i', $userAgent, $match)) {
        $osName = 'Android';
        $osVersion = (string) $match[1];
    } elseif (preg_match('/cpu (?:iphone )?os\s*([0-9_]+)/i', $userAgent, $match)) {
        $osName = 'iOS';
        $osVersion = str_replace('_', '.', (string) $match[1]);
    } elseif (preg_match('/mac os x\s*([0-9_\.]+)/i', $userAgent, $match)) {
        $osName = 'macOS';
        $osVersion = str_replace('_', '.', (string) $match[1]);
    } elseif (strpos($ua, 'linux') !== false) {
        $osName = 'Linux';
    }

    $browserName = 'Unknown';
    $browserVersion = '-';
    if (preg_match('/edg\/([0-9\.]+)/i', $userAgent, $match)) {
        $browserName = 'Microsoft Edge';
        $browserVersion = (string) $match[1];
    } elseif (preg_match('/opr\/([0-9\.]+)/i', $userAgent, $match)) {
        $browserName = 'Opera';
        $browserVersion = (string) $match[1];
    } elseif (preg_match('/chrome\/([0-9\.]+)/i', $userAgent, $match)) {
        $browserName = 'Google Chrome';
        $browserVersion = (string) $match[1];
    } elseif (preg_match('/firefox\/([0-9\.]+)/i', $userAgent, $match)) {
        $browserName = 'Mozilla Firefox';
        $browserVersion = (string) $match[1];
    } elseif (preg_match('/version\/([0-9\.]+).*safari/i', $userAgent, $match)) {
        $browserName = 'Safari';
        $browserVersion = (string) $match[1];
    } elseif (preg_match('/msie\s*([0-9\.]+)/i', $userAgent, $match)) {
        $browserName = 'Internet Explorer';
        $browserVersion = (string) $match[1];
    }

    return [
        'device_type' => $deviceType,
        'device_name' => substr($deviceName, 0, 80),
        'os_name' => substr($osName, 0, 60),
        'os_version' => substr($osVersion, 0, 40),
        'browser_name' => substr($browserName, 0, 80),
        'browser_version' => substr($browserVersion, 0, 40),
    ];
}