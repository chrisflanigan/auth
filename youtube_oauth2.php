<?php
$config = include(dirname(__FILE__).'/config.php');
// Change these
define('CLIENT_ID',         $config['youtube']['clientId']);
define('CLIENT_SECRET',     $config['youtube']['clientSecret']);
define('REDIRECT_URI',      ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")?'https':'http').'://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME']);
define(
    'SCOPE',
    implode(
        ' ',
        array(
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtubepartner-channel-audit',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/youtube.readonly'
        )
    )
);

// You'll probably use a database
session_name('youtube');
session_start();

// OAuth 2 Control Flow
if (isset($_GET['error'])) {
    // returned an error
    print $_GET['error'] . ': ' . $_GET['error_description'];
    exit;
} elseif (isset($_GET['code'])) {
    // User authorized your application
    if ($_SESSION['state'] == $_GET['state']) {
        // Get token so you can make API calls
        getAccessToken();
    } else {
        // CSRF attack? Or did you mix up your states?
        exit;
    }
} else {
    if ((empty($_SESSION['expires_at'])) || (time() > $_SESSION['expires_at'])) {
        // Token has expired, clear the state
        $_SESSION = array();
    }
    if (empty($_SESSION['access_token'])) {
        // Start authorization process
        getAuthorizationCode();
    }
}

// Congratulations! You have a valid token. Now fetch your profile
//$user = fetch('GET', '/v1/people/~:(id,first-name,last-name,headline,email-address,picture-url,industry,site-standard-profile-request,interests,summary,main-address,phone-numbers,skills:(skill))');
var_dump($_SESSION);die;

function getAuthorizationCode() {
    $params = array(
        'response_type' => 'code',
        'client_id'     => CLIENT_ID,
        'state'         => uniqid('', true), // unique long string
        'redirect_uri'  => REDIRECT_URI,
        'scope'         => SCOPE,
        'access_type'   => 'offline',
    );

    // Authentication request
    $url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);

    // Needed to identify request when it returns to us
    $_SESSION['state'] = $params['state'];

    // Redirect user to authenticate
    header("Location: $url");
    exit;
}

function getAccessToken() {
    $params = array(
        'grant_type'    => 'authorization_code',
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'code'          => $_GET['code'],
        'redirect_uri'  => REDIRECT_URI,
    );

    // Access Token request
    $url = 'https://accounts.google.com/o/oauth2/token';

    // Tell streams to make a POST request
    $data = http_build_query($params);
    $context = stream_context_create(
        array(
            'http' =>
                array(
                    'method' => 'POST',
                    'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
                        . "Content-Length: " . strlen($data) . "\r\n",
                    'content' => $data,
            )
        )
    );

    // Retrieve access token information
    $response = file_get_contents($url, false, $context);

    // Native PHP object, please
    $token = json_decode($response);

    // Store access token and expiration time
    $_SESSION['access_token'] = $token->access_token; // guard this!
    $_SESSION['expires_in']   = $token->expires_in; // relative time (in seconds)
    $_SESSION['expires_at']   = time() + $_SESSION['expires_in']; // absolute time

    return true;
}

/*
function fetch($method, $resource, $body = '') {
    $params = array('oauth2_access_token' => $_SESSION['access_token'],
        'format' => 'json',
    );

    // Need to use HTTPS
    $url = 'https://api.linkedin.com' . $resource . '?' . http_build_query($params);
    // Tell streams to make a (GET, POST, PUT, or DELETE) request
    $context = stream_context_create(
        array('http' =>
            array('method' => $method,
            )
        )
    );

    // Hocus Pocus
    $response = file_get_contents($url, false, $context);

    // Native PHP object, please
    return json_decode($response);
}
*/
