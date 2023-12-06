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
        $oClient = getBaseClient();
        file_put_contents(getTempAuthPath(),$_GET['code']);
        echo "Moved the auth code to temp, run the programing from CLI now<br/>\n";
        die('paste following code: ' . htmlentities($_GET['code']));
    }

    //throw new Exception('This application must be run on the command line.');
}
/**
 * @return Google_Client
 */
function getBaseClient()
{
    global $vLocalEmail;
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    if (file_exists(CLIENT_SECRET_PATH)){
        $client->setAuthConfig(CLIENT_SECRET_PATH);
    }
    if (!empty($vLocalEmail)){
        $client->setconfig('subject',$vLocalEmail);
    }
    $client->setAccessType('offline');

    $client->setRedirectUri('http://gmail.local.com');
    return $client;
}
function writeClientAuth(Google_Client $client,
    $authCode)
{
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    // Exchange authorization code for an access token.
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    if (isset($accessToken['error'])){
        print_r($accessToken);
        $vTempPath = getTempAuthPath();
        $vMessage = "";
        if (file_exists($vTempPath)){
            $vMessage = " and delete $vTempPath ";
        }
        echo "consider re-generating auth token $vMessage \n";
        die;
    }
    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
        mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, json_encode($accessToken));
    printf("Credentials saved to %s\n", $credentialsPath);
}
function getTempAuthPath()
{
    $vDir = '/tmp/gmail';
    $vFilePath = $vDir . '/tmp.auth';
    if (!file_exists($vDir)) {
        mkdir($vDir, 0777, true);
        chmod($vDir, 0777);
    }
    return $vFilePath;
}
function moveCredentials()
{
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    $vTempAuthPath = getTempAuthPath();
    if (!file_exists($credentialsPath) &&
    file_exists($vTempAuthPath)){
        $vCredentials = file_get_contents($vTempAuthPath);
        writeClientAuth(getBaseClient(),$vCredentials );
        unlink($vTempAuthPath);
        echo 'credentials moved rerun the program';
        exit;
    }
}
/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
    $client = getBaseClient();
    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        moveCredentials();
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        $vShellCommand = "x-www-browser \"$authUrl\"";
        shell_exec($vShellCommand);
        echo "Trying to directly open url by $vShellCommand\n";
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));
        writeClientAuth($client, $authCode);
        echo "Rerun the program";
        die;

    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        if (!$client->getRefreshToken()){
            unlink($credentialsPath);
            throw new Exception('refresh token not found. deleted ' . $credentialsPath . ' retry');
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

fwrite(STDERR, "about to get emails 500 at a time\n\n");


$results = getService()->users_messages->listUsersMessages(getUser(), [
    'q'=>'in:inbox',
    'maxResults'=>'500', //seems to be the limit
]);
fwrite(STDERR, "emails retrieved\n\n");
$aMessages = $results->getMessages();
fwrite(STDERR, "message list retrieved \n\n");

$aMap = [];

/** @var Google_Service_Gmail_Message $oMessage */
foreach ($aMessages as $oMessage) {

    $aHeaders = getMessageHeaders($oMessage);

    getEmailsFromHeaderIndex($aMap,$aHeaders,'To',$oMessage);
    getEmailsFromHeaderIndex($aMap,$aHeaders,'From',$oMessage);

}
fwrite(STDERR,"all individual messages retrieved\n\n");

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
    $aToSummarize = $aMap[$vIndexName];
    $aDisplay = [];
    foreach ($aToSummarize as $vEmail => $aThreads) {
        $aDisplay[$vEmail] = count($aThreads);
    }

    asort($aDisplay);
    $aDisplayReverse = array_reverse($aDisplay,true);
    //echo "Displaying $vIndexName\n";
    //print_r($aDisplayReverse);
    showGmailQuery($aDisplayReverse, $vIndexName);
}

function showGmailQuery($aDisplay,$vIndexName)
{
    $vSeparator = "";
    echo "zainGmailTest.queries  = [";
    foreach ($aDisplay as $vEmail => $iCount) {
        if ($iCount <2){
            continue;
        }
        echo $vSeparator;
        echo "'in:inbox $vIndexName:$vEmail'";
        $vSeparator = ",\n";
    }
    echo "];\n";
    echo "zainGmailTest.queryIndex =-1\n";
    echo "zainGmailTest.goNext();\n";

}
showIndex($aMap, 'From');
//showIndex($aMap, 'To');

function getEmailsFromHeaderIndex(&$aMap,$aHeaders,$vIndexName,
                                  Google_Service_Gmail_Message $oMessage){
    if (!isset($aHeaders[$vIndexName])){
        return;
    }
    $aEmails = gmailEmailsFromText($aHeaders[$vIndexName]);
    updateCountOfElement($aMap,$vIndexName, $aEmails,$oMessage);
}
function updateCountOfElement(&$aMap,$vIndex,$aData,
                              Google_Service_Gmail_Message $oMessage)
{
    global $vLocalEmail;
    foreach ($aData as $vEmail) {
        $vEmail = strtolower($vEmail);
        if (($vIndex == 'To') && ($vEmail === $vLocalEmail)){
            continue;
        }
        if (!isset($aMap[$vIndex][$vEmail])){
            $aMap[$vIndex][$vEmail] = [];
        }
        $aMap[$vIndex][$vEmail][$oMessage->getThreadId()] = $oMessage->getThreadId();
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

