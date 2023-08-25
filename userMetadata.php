<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Set a metadata field on the current authenticated user
 */
function setField($key, $value)
{
    $host = getenv('Z_URL') ? getenv('Z_URL') : 'http://localhost:8080';
    $bearer = getenv('PAT');
    if (!$bearer) {
        throw new Exception('PAT not defined');
    }

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Unathorised');
    }
    $token = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));

    if (!$key || !$value) {
        throw new Exception('Key or value not passed');
    }

    // Get the keys
    $openidConfig = file_get_contents($host . '/oauth/v2/keys');
    $jwks = json_decode($openidConfig, true);
    // Decode JWT and get the userId out of it
    $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
    $userId = $decoded->sub;

    if (!$userId) {
        throw new Exception('Couldn\'t authenticate the user');
    }

    // Make the actual request using the PAT token of the service user that is allowed to make such requests
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $host . '/management/v1/users/' . $userId . '/metadata/' . $key,
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
    curl_close($curl);
}

function getField($key)
{
    $host = getenv('Z_URL') ? getenv('Z_URL') : 'http://localhost:8080';
    $bearer = getenv('PAT');
    if (!$bearer) {
        throw new Exception('PAT not defined');
    }

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Unathorised');
    }
    $token = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));

    if (!$key) {
        throw new Exception('Key not passed');
    }

    // Get the keys
    $openidConfig = file_get_contents($host . '/oauth/v2/keys');
    $jwks = json_decode($openidConfig, true);
    // Decode JWT and get the userId out of it
    $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
    $userId = $decoded->sub;

    if (!$userId) {
        throw new Exception('Couldn\'t authenticate the user');
    }

    $response = file_get_contents($host . '/management/v1/users/' . $userId . '/metadata/' . $key, false, stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => "accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer $bearer\r\n",
            "ignore_errors" => true
        ]
    ]));
    if (!isset($response['metadata']['value'])) {
        return [];
    }
    return $response['metadata']['value'] || [];
}

function getFields()
{
    $host = getenv('Z_URL') ? getenv('Z_URL') : 'http://localhost:8080';
    $bearer = getenv('PAT');
    if (!$bearer) {
        throw new Exception('PAT not defined');
    }

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Unathorised');
    }
    $token = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));

    // Get the keys
    $openidConfig = file_get_contents($host . '/oauth/v2/keys');
    $jwks = json_decode($openidConfig, true);
    // Decode JWT and get the userId out of it
    $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
    $userId = $decoded->sub;

    if (!$userId) {
        throw new Exception('Couldn\'t authenticate the user');
    }

    $response = file_get_contents($host . '/management/v1/users/' . $userId . '/metadata/_search', false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer $bearer\r\n",
            "ignore_errors" => true
        ]
    ]));
    $response = json_decode($response, true);
    $res = [];
    if (isset($response['result'])) {
        foreach ($response['result'] as $i) {
            $res[$i['key']] = json_decode(base64_decode($i['value']));
        }
    }
    return $res;
}

