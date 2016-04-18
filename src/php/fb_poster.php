<?php

# Install FB SDK as follows: https://developers.facebook.com/docs/php/gettingstarted#installation
require_once __DIR__ . '/../setup/vendor/autoload.php';

$dbgMode = false;

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookAuthenticationException;


function mylog($dbgMode, $message) {
    if (!$dbgMode) return;
    echo $message;
}



$app_id = '420697011358412';
$app_secret = 'ENTER APP SECRET HERE';

# This script was setup under local apache server under the below URL which was added to the FB app
$my_url = 'http://localhost/personas/src/fb_poster.php';



// Init PHP Sessions
session_start();



function request_permissions() {
    global $helper, $my_url;
    $permissions = ['publish_actions'];
    $loginUrl = $helper->getLoginUrl($my_url, $permissions);
    echo '<br/><br/><a href="' . $loginUrl . '">Log in with Facebook</a><br/><br/>';
    return;
}


// https://developers.facebook.com/docs/php/gettingstarted

$fb = new Facebook([
    'app_id' => $app_id,
    'app_secret' => $app_secret,
    'default_graph_version' => 'v2.5',
]);

$sessionToken = $_SESSION['facebook_access_token'];

mylog($dbgMode, "Current access token is: " . $sessionToken . "<br/>");




if (!isset($sessionToken)) {

    // Handle expired access token

    // https://developers.facebook.com/blog/post/2011/05/13/how-to--handle-expired-access-tokens/


    // This wrapper function exists in order to circumvent PHP’s
    // strict obeying of HTTP error codes. In this case, Facebook
    // returns error code 400 which PHP obeys and wipes out
    // the response
    function curl_get_file_contents($URL) {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        $err  = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($contents) return $contents;
        else return FALSE;
    }


    // If we get a code, it means that we have re-authed the user and can get a valid access_token.
    if (isset($_REQUEST["code"])) {
        mylog($dbgMode, "<br/><br/>Request code is " . $_REQUEST['code'] . "<br/><br/>");

        $token_url="https://graph.facebook.com/oauth/access_token?"
            . "client_id=" . $app_id
            . "&redirect_uri=" . urlencode($my_url)
            . "&client_secret=" . $app_secret
            . "&code=" . $_REQUEST["code"] . "&display=popup";
        $response = file_get_contents($token_url);
        $params = null;
        parse_str($response, $params);

        // Reset the token in the session, somehow PHP is not doing this automatically
        $sessionToken = $_SESSION['facebook_access_token'] = $params['access_token'];
    }



    // Attempt to query the graph:
    $graph_url = "https://graph.facebook.com/me?access_token=" . $sessionToken;
    $response = curl_get_file_contents($graph_url);
    $decoded_response = json_decode($response);


    mylog($dbgMode, "<br/><br/>Decoded response = <br/><br/>");
    // var_dump($decoded_response);
    mylog($dbgMode, "<br\><br/>");




    // Check for errors
    if (property_exists($decoded_response, 'error')) {

        // Check to see if this is an oAuth error:
        if ($decoded_response->error->type== "OAuthException") {
            // Retrieve a valid access token.
            $dialog_url= "https://www.facebook.com/dialog/oauth?"
                . "client_id=" . $app_id
                . "&redirect_uri=" . urlencode($my_url);
            echo("<script> top.location.href='" . $dialog_url
                . "'</script>");
        }
        else {
            mylog($dbgMode, "<br/><br/>Some other unknown error has occurred. Exiting<br/><br/>");
            exit;
        }
    }


    // success
    mylog($dbgMode, "<br/><br/>Successfully got a response<br/><br/>");
    mylog($dbgMode, "<br/><br/>New access token is = " . $sessionToken . "<br/><br/>");



    // https://api.facebook.com/restserver.php?method=auth.expireSession&format=json&access_token=EAAFZBnyfriswBAKZC1WybzPhMrSBNjr2AWdkKLPpXDosagxhZALlDnZAp66vx8124IVx1BY4VQG7iCJTHZAE2TOhV14SmJtwwFzkraZAACw4CA85EtwCNlUW7DnUM5NVoWh73FWNTmFqOpQcQ0DAJZBVLxrMuycNhYZD


    // https://developers.facebook.com/docs/marketing-api/quickstart
    $helper = $fb->getRedirectLoginHelper();

    try {
        mylog($dbgMode, "<br/>Trying to get a session token from FB<br/>");
        $sessionToken = (string) $helper->getAccessToken();
        mylog($dbgMode, "<br/>Got token = $sessionToken<br/>");
    } catch(FacebookAuthenticationException $e) {
        mylog($dbgMode, 'Graph returned an auth error: ' . $e->getMessage() . '<br/>');

        request_permissions();

        exit;
    } catch(FacebookResponseException $e) {
        // When Graph returns an error
        mylog($dbgMode, 'Graph returned a response error: ' . $e->getMessage() . '<br/>');
        exit;
    } catch(FacebookSDKException $e) {
        // When validation fails or other local issues
        mylog($dbgMode, 'Facebook SDK returned an error: ' . $e->getMessage() . '<br/>');
        exit;
    }
} else
{
    mylog($dbgMode, "<br/><br/>You're logged in!<br/><br/>");
}



// https://developers.facebook.com/docs/php/howto/postwithgraphapi
$linkData = [
    //'message' => 'User provided message at time = ' . time(),
    'link' => 'https://s3.amazonaws.com/uploads.hipchat.com/23178/2425248/Ey5ufCSn7IUQye6/Slide5.jpg',
    //'picture' => 'https://doc-0s-9c-docs.googleusercontent.com/docs/securesc/dhkit8h3bhop6ne566rpb5asdpjcjsss/8e54g5v8uauotcs76pacb7lh6f8tflk8/1460865600000/09469294460855078656/09469294460855078656/0B7xWOPb7nexLT0NZRURqSW1YcFU',
    //'name' => 'YESSS!',
    'caption' => ' ',
    //'description' => 'DESC'
];

try {
    // Returns a `Facebook\FacebookResponse` object
    $response = $fb->post('/me/feed', $linkData, $sessionToken);
} catch(FacebookAuthenticationException $e) {
    mylog($dbgMode, 'Graph returned an auth error: ' . $e->getMessage() . '<br/>');

    request_permissions();

    exit;
} catch(FacebookResponseException $e) {
    mylog($dbgMode, 'Graph returned a response error: ' . $e->getMessage() . '<br/>');
    exit;
} catch(FacebookSDKException $e) {
    mylog($dbgMode, 'Facebook SDK returned an error: ' . $e->getMessage() . '<br/>');
    exit;
}

$graphNode = $response->getGraphNode();
echo '<br/><br/>Posted with id: ' . $graphNode['id'] . "<br/><br/>";











?>
