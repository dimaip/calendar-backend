<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

function base64_url_encode($input)
{
    return strtr(base64_encode($input), '+/=', '-_.');
}

function base64_url_decode($input)
{
    return base64_decode(strtr($input, '-_.', '+/='));
}

function decodeB64($string)
{
    $prefix = 'b64_';
    if (substr($string, 0, strlen($prefix)) === $prefix) {
        return base64_url_decode(substr($string, strlen($prefix)));
    }
    return $string;
}

function makeGetRequest($url, $method = 'GET', $authenticated = false, $skipDecode = false)
{
    $host = getenv('Z_URL') ? getenv('Z_URL') : 'http://localhost:8080';
    $bearer = getenv('PAT');
    if ($authenticated && !$bearer) {
        throw new Exception('PAT not defined');
    }
    $context = $authenticated ? stream_context_create([
        "http" => [
            "method" => $method,
            "header" => "accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer $bearer\r\n",
            "ignore_errors" => false
        ]
    ]) : null;
    $content = file_get_contents($host . $url, false, $context);
    return $skipDecode ? $content : json_decode($content, true);
}

function getUserId()
{
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Unathorised');
    }
    $token = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));

    // Get the keys
    $jwks = makeGetRequest('/oauth/v2/keys');

    // Decode JWT and get the userId out of it
    $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
    $userId = $decoded->sub;

    if (!$userId) {
        error_log('Couldn\'t authenticate the user');
        throw new Exception('Couldn\'t authenticate the user');
    }
    return $userId;
}

/**
 * Set a metadata field on the current authenticated user
 */
function setField($key, $value)
{
    $host = getenv('Z_URL') ? getenv('Z_URL') : 'http://localhost:8080';
    $bearer = getenv('PAT');

    try {
        $userId = getUserId();
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(401);
        return [
            "errorCode" => "jwt_expired",
            "errorMessage" => "JWT token expired"
        ];
    }

    // Make the actual request using the PAT token of the service user that is allowed to make such requests
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $host . '/management/v1/users/' . $userId . '/metadata/' . 'b64_' . base64_url_encode($key),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "value" => base64_encode($value)
        ]),
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json',
            "Authorization: Bearer $bearer"
        ),
    ));

    curl_exec($curl);
    $output = curl_exec($curl);
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 400) {
        error_log(json_encode($output));
        throw new Exception('Request failed');
    }
    curl_close($curl);
}

function getFieldsForUser($userId)
{
    try {
        $response = makeGetRequest('/management/v1/users/' . $userId . '/metadata/_search', 'POST', true);
        $res = [];
        if (isset($response['result'])) {
            foreach ($response['result'] as $i) {
                $key = decodeB64($i['key']);
                $res[$key] = json_decode(base64_decode($i['value']), true);
            }
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(400);
        return [
            "errorCode" => "zitadel_metadata_request_failed",
            "errorMessage" => "Zitadel profile call failed"
        ];
    }
    return $res;
}

function getFields()
{
    try {
        $userId = getUserId();
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(401);
        return [
            "errorCode" => "jwt_expired",
            "errorMessage" => "JWT token expired"
        ];
    }

    return getFieldsForUser($userId);
}

function compileServiceStructure($userId, $serviceId, $versionId)
{
    $state = getFieldsForUser($userId);

    // Extract script version details based on versionId
    $scriptVersions = array_values(array_filter(
        $state["scriptVersions__\"$serviceId\""] ?? [],
        function ($version) use ($versionId) {
            return $version['id'] == $versionId;
        }
    ));
    $scriptVersion = reset($scriptVersions);

    // Prepare the initial structure
    $structure = [
        'userId' => $userId,
        'scriptVersionId' => $scriptVersion['id'] ?? '',
        'scriptVersionName' => $scriptVersion['name'] ?? '',
        'service' => $serviceId,
        'customPrayers' => $state["customPrayers__\"$serviceId\""] ?? [],
        'disabledPrayers' => [],
        'extraPrayers' => []
    ];

    // Filter disabled prayers
    if (!empty($state['disabledPrayers'])) {
        $structure['disabledPrayers'] = array_values(array_filter(
            $state['disabledPrayers'],
            function ($prayer) use ($serviceId, $versionId) {
                return strpos($prayer, $serviceId) !== false && strpos($prayer, (string)$versionId) !== false;
            }
        ));
    }

    // Extract extra prayers
    $extraPrayerKeys = array_values(array_filter(
        array_keys($state),
        function ($key) use ($serviceId, $versionId) {
            return strpos($key, "extraPrayers__") !== false &&
                strpos($key, "$serviceId-$versionId") !== false;
        }
    ));
    foreach ($extraPrayerKeys as $key) {
        $structure['extraPrayers'][substr($key, 15, -1)] = $state[$key];
    }

    $extraPrayerIds = array_merge(...array_values($structure['extraPrayers'] ?? []));

    $structure['customPrayers'] = array_values(array_filter($state["customPrayers__\"Sugubaja\""], function ($prayer) use ($extraPrayerIds) {
        return in_array($prayer['id'], $extraPrayerIds);
    }));

    return $structure;
}
