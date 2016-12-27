<?php
require_once __DIR__ . '/vendor/autoload.php';

define('APPLICATION_NAME', 'Gmail API PHP Quickstart');
define('CREDENTIALS_PATH', '~/.credentials/gmail-php-quickstart.json');
define('CLIENT_SECRET_PATH', expandHomeDirectory('~/.credentials/client_secret.json'));
// If modifying these scopes, delete your previously saved credentials
// at ~/.credentials/gmail-php-quickstart.json
define('SCOPES', implode(' ', array(
        Google_Service_Gmail::GMAIL_READONLY)
));

if (php_sapi_name() != 'cli') {
    if (empty($_GET['code'])){
        die("use it from command line");
    }
    else{
        die('paste following code: ' . htmlentities($_GET['code']));
    }

    //throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    if (file_exists(CLIENT_SECRET_PATH)){
        $client->setAuthConfig(CLIENT_SECRET_PATH);
    }
    $client->setAccessType('offline');

    $client->setRedirectUri('http://gmail.local.com');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Gmail($client);

// Print the labels in the user's account.
$user = 'me';
$results = $service->users_messages->listUsersMessages($user, [
    'q'=>'in:inbox',
    'maxResults'=>'500', //seems to be limit
]);

$aMessages = $results->getMessages();
/** @var Google_Service_Gmail_Message $oMessage */
foreach ($aMessages as $oMessage) {
    $oMessage = $service->users_messages->get($user, $oMessage->getId(),[
        'format'=>'metadata',
        'metadataHeaders'=>['From', 'Subject','To'],
        //'metadataHeaders'=>'From',
    ]);
    /** @var Google_Service_Gmail_MessagePart $oPayload */
    $oPayload = $oMessage->getPayload();
    $aHeadersApi = $oPayload->getHeaders();
    $aHeaders = [];
    /** @var Google_Service_Gmail_MessagePartHeader $oServiceHeader */
    foreach ($aHeadersApi as $oServiceHeader) {
        $aHeaders[$oServiceHeader->getName()] = $oServiceHeader->getValue();
    }

    $debug = 1;

}

