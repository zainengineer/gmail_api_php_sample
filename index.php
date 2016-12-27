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
$vLocalConfig = dirname(__FILE__) . '/local.config.json';
if (file_exists($vLocalConfig)){
    $vLocalConfig = file_get_contents($vLocalConfig);
    $aLocalConfig = json_decode($vLocalConfig,true);
    $vLocalEmail = $aLocalConfig['email'];
}
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
        if (!$client->getRefreshToken()){
            throw new Exception('refresh token not found. delete ' . $credentialsPath);
        }
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

function getUser()
{
    return $user = 'me';
}


$results = getService()->users_messages->listUsersMessages(getUser(), [
    'q'=>'in:inbox',
    'maxResults'=>'500', //seems to be limit
]);

$aMessages = $results->getMessages();

$aMap = [];

/** @var Google_Service_Gmail_Message $oMessage */
foreach ($aMessages as $oMessage) {

    $aHeaders = getMessageHeaders($oMessage);

    getEmailsFromHeaderIndex($aMap,$aHeaders,'To');
    getEmailsFromHeaderIndex($aMap,$aHeaders,'From');

}
function getService()
{
    static $service;
    if (!$service){
        // Get the API client and construct the service object.
        $client = getClient();
        $service = new Google_Service_Gmail($client);
    }
    return $service;
}

function getCache($vKey)
{
    return @unserialize(file_get_contents(getCacheKeyPath($vKey)));
}

function putCache($vKey, $value)
{

    file_put_contents(getCacheKeyPath($vKey), serialize($value));
}
function getCacheKeyPath($vKey)
{
    $vDir = dirname(__FILE__) . '/.local/cache';
    if (!file_exists($vDir)){
        mkdir($vDir,0777,true);
    }
    $vFile = $vDir . '/' . sha1($vKey);
    return $vFile;
}

function getMessageHeaders(Google_Service_Gmail_Message $oMessage )
{
    $vKey = 'message -' . $oMessage->getId();
    if ($return = getCache($vKey)){
        return $return;
    }
    $oMessage = getService()->users_messages->get(getUser(), $oMessage->getId(),[
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
        $vHeaderName = $oServiceHeader->getName();
        $HeaderValue = $oServiceHeader->getValue();
        $aHeaders[$vHeaderName] = $HeaderValue;
    }
    putCache($vKey, $aHeaders);
    return $aHeaders;
}

function showIndex($aMap,$vIndexName){
    if (!isset($aMap[$vIndexName])){
        echo "map not found $vIndexName\n";
        return ;
    }
    $aDisplay = $aMap[$vIndexName];
    asort($aDisplay);
    $aDisplayReverse = array_reverse($aDisplay,true);
    echo "Displaying $vIndexName\n";
    print_r($aDisplayReverse);
}
showIndex($aMap, 'To');
showIndex($aMap, 'From');

function getEmailsFromHeaderIndex(&$aMap,$aHeaders,$vIndexName){
    if (!isset($aHeaders[$vIndexName])){
        return;
    }
    $aEmails = gmailEmailsFromText($aHeaders[$vIndexName]);
    updateCountOfElement($aMap,$vIndexName, $aEmails);
}
function updateCountOfElement(&$aMap,$vIndex,$aData)
{
    global $vLocalEmail;
    foreach ($aData as $vEmail) {
        $vEmail = strtolower($vEmail);
        if (($vIndex == 'To') && ($vEmail === $vLocalEmail)){
            continue;
        }
        if (!isset($aMap[$vIndex][$vEmail])){
            $aMap[$vIndex][$vEmail] = 0;
        }
        $aMap[$vIndex][$vEmail]++;
    }
}
function gmailEmailsFromText($string)
{
    // this regex handles more email address formats like a+b@google.com.sg, and the i makes it case insensitive
    //$pattern = '/[a-z0-9_\-\+]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i';
    $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/';

// preg_match_all returns an associative array
    preg_match_all($pattern, $string, $matches);

    return $matches[0];

}

