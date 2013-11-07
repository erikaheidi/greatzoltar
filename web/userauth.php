<?php
/**
 * Authorize a user for this application and show the user access tokens.
 *
 * INSTRUCTIONS:
 * Replace CONSUMER_KEY and CONSUMER_SECRET with your application tokens.
 * Access the script (dont forget to point the callback url on the application settings to this script)
 * Authorize the application with the bot user
 * Get the Access Tokens that will show up here and fill config.yml.example with your keys
 * Save as config.yml and run php app/console zoltar:fetch
 */

session_start();
if (isset($_GET['logout']))
    session_unset();

require( __DIR__ . '/../vendor/autoload.php');

$config = array(
    'consumer_key'    => 'CONSUMER_KEY',
    'consumer_secret' => 'CONSUMER_SECRET'
);

$app = new \TTools\App($config);

if ($app->isLogged()) {
    $user = $app->getCurrentUser();

    list($token, $token_secret) = $app->getUserTokens();

    echo "<h3>Logged in as @". $user['screen_name'] . "</h3>";
    echo "<p>Your Access Token: $token" ;
    echo "<p>Your Access Token Secret: $token_secret" ;
    echo '<p>[ <a href=".?logout=1">Logout</a> ]';

} else {
    $login_url = $app->getLoginUrl();
    echo 'Please log in to get your keys: <a href="'. $login_url . '">' . $login_url . '</a>';
}