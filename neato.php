<?php

/*************************************************************************************/
/*                       			### Script Neato ###                             */
/*                                                                                   */
/*                         https://github.com/job-so/eedomus-neato/                  */
/*************************************************************************************/
//

function sdk_neato_request($url, $payload = array(), $method = "POST", $headers = array())
{
	$requestHeaders = array(
		'Content-Type: application/json',
		'Accept: application/vnd.neato.nucleo.v1'
	);

	if (count($headers) > 0) {
		$requestHeaders = array_merge($requestHeaders, $headers);
	}
	$jsonResponse = httpQuery($url, $method, $payload, null, $requestHeaders);
	return $jsonResponse;
}

function sdk_neato_doAction($command, $params = false)
{
	$result = array("message" => "no serial or secret");
	global $baseUrl, $serial, $secret;

	if ($serial !== false && $secret !== false) {
		$payload = "{";
		$payload .= '"reqId":"' . uniqid() . '","cmd":"' . $command . '"';
		if ($params !== false) {
			$payload .= ',"params":' . $params . '';
		}
		$payload .= "}";

		$date = gmdate("D, d M Y H:i:s")." GMT";
		$data = implode("\n", array(strtolower($serial), $date, $payload));
		$hmac = hash_hmac("sha256", $data, $secret);

		$headers = array(
			"Date: " . $date,
			"Authorization: NEATOAPP " . $hmac
		);

		$result = sdk_neato_request($baseUrl . "/vendors/neato/robots/" . $serial . "/messages", $payload, "POST", $headers);
	}

	return $result;
}

function sdk_neato_login($username, $password)
{
	$url = "https://beehive.neatocloud.com/sessions";
	$payload = "{";
	$payload .= '"email": "' . $username . '",';
	$payload .= '"password": "' . $password . '"';
	$payload .= "}";
	$jsonResponse = sdk_neato_request($url,  $payload);
	$response = sdk_json_decode($jsonResponse);
	if (array_key_exists('message', $response)) {
		sdk_neato_displayLoginForm('Erreur de connexion', $response['message']);
	} else {
		return $response;
	}
}

function sdk_neato_listRobots()
{
	if (empty($_POST['submit'])) {
		sdk_neato_displayLoginForm('Veuillez renseigner vos identifiants Neato.');
	}
	$session = sdk_neato_login($_POST['username'], $_POST['password']);
	$url = "https://beehive.neatocloud.com/users/me/robots";
	$headers = array(
		"Authorization: Bearer " . $session['access_token']
	);
	$jsonResponse = sdk_neato_request($url, null, "GET", $headers);
	$response = sdk_json_decode($jsonResponse);
	if (array_key_exists('message', $response)) {
		sdk_neato_displayLoginForm('Erreur de connexion', $response['message']);
	} else {
		sdk_neato_displayRobotsForm($response);
	}
}

function sdk_neato_displayRobotsForm($robotsArray)
{
	echo '<html><head><title>eedomus neato Robots List</title></head><body>';
	echo count($robotsArray);
	echo '<br />';
	$robotsKeys = array_keys($robotsArray);
	echo '<table border=1><tr>';
	foreach ($robotsKeys as &$key) {
		echo '<td><a href="?exec=neato.php&command=selectRobot&baseUrl='.$robotsArray[$key]['nucleo_url'].'&serial='.$robotsArray[$key]['serial'].'&secret='.$robotsArray[$key]['secret_key'].'">SELECTIONNER</a></td>';
		echo '<td>'.$robotsArray[$key]['name'].'</td>';
		echo '<td>'.$robotsArray[$key]['model'].'</td>';
		echo '<td>'.$robotsArray[$key]['serial'].'</td>';
		echo '<td>'.$robotsArray[$key]['firmware'].'</td>';
		echo '<td>'.$robotsArray[$key]['secret_key'].'</td>';
		echo '<td>'.$robotsArray[$key]['nucleo_url'].'</td>';
		echo '<td>'.$robotsArray[$key]['mac_address'].'</td>';
		echo '<td>'.$robotsArray[$key]['created_at'].'</td>';
	}
	echo '</tr></table>';
	echo '</body></html>';
	die;
}

function sdk_neato_displayLoginForm($message = '', $error = '')
{
	echo '<html><head><title>eedomus neato</title></head><body>';
	if (!empty($message)) echo '<p><b>' . $message . '</b></p>';
	if (!empty($error)) echo '<p style="color:red"><b>' . $error . '</b></p>';
	echo '<form method="post">';
	echo 'Nom d\'utilisateur ' . ' :<br><input type="text" name="username" value="' . loadVariable('username') . '"><br><br>';
	echo 'Mot de passe ' . ' :<br><input type="password" name="password" value="' . loadVariable('password') . '"><br><br>';
	echo '<input type="submit" name="submit" value="' . 'Connexion' . '">';
	echo '</body>';
	die;
}

function sdk_neato_selectRobot()
{
	$baseUrl = getArg('baseUrl', true);
	$serial = getArg('serial', true);
	$secret = getArg('secret', true);
	saveVariable('baseUrl', $baseUrl);
	saveVariable('serial', $serial);
	saveVariable('secret', $secret);
	echo "your robot has been registered<br />";
	// echo "<a href=''>Clicquez Ici pour revenir à votre Périphérique</a>"

}

function sdk_neato_startCleaning()
{
	
}

// main()

$command = getArg('command', false, 'defaultCommand');
$params =  getArg('params', false, false);

if ($command === 'listRobots') {
	sdk_neato_listRobots();
	die;
}
if ($command === 'selectRobot') {
	sdk_neato_selectRobot();
	die;
}
$baseUrl = loadVariable('baseUrl');
$serial = loadVariable('serial');
$secret = loadVariable('secret');

if ($command === 'defaultCommand') {
    sdk_header('text/xml');
	echo "<error>Please Read The Documentation</error>";
	die;
}

if ($command === 'getRobotState') {
    sdk_header('text/xml');
    $jsonRobotState = sdk_neato_doAction($command, $params);
    $robotState = sdk_json_decode($jsonRobotState);

    $robotState['status'] = 'ok';
    if ($robotState['result'] !== 'ok') {
	    $robotState['status'] = 'ko';
    } else {
        switch ($robotState['state']) {
            case 1:
                $robotState['status'] = 'stopped';
                if ($robotState['details']['isDocked']) {
                    $robotState['status'] = 'docked';
                }
                if ($robotState['details']['isCharging']) {
                    $robotState['status'] = 'charging';
                }
                break;
            case 2:
                $robotState['status'] = 'cleaning';
                break;
            case 3:
                $robotState['status'] = 'suspended';
                break;
            case 4:
                $robotState['status'] = $robotState['error'];
        }
    }
    echo ('<?xml version="1.0" encoding="ISO-8859-1"?>');
    echo ('<root>');
    echo ('<status>'.$robotState['status'].'</status>');
    echo ('<raw>'.$jsonRobotState.'</raw>');
    echo ('</root>');
    // echo (jsonToXML($jsonRobotState));
    die;
}

if ($command === 'startCleaning' || $command === 'houseCleaning') {
    // mode: 1 eco, 2 turbo (used if applicable to service version)
    // navigation_mode: 1 normal, 2 extra care, 3 deep (used if applicable to service version)
	// modifier	: The cleaning frequency. Only 1 normal is allowed.
    // category: 2 non-persistent map, 3 spot cleaning, 4 persistent map / Fixed to 2 for house cleaning.
    // boundary_id: (option for basic-3 & basic-4) the id of the zone to clean
    // map_id: (option for basic-3 & basic-4)) the id of the map to clean
	// spot_width: spot width in cm
    // spot_height: spot height in cm

	$command = 'startCleaning';
	// Get Robot Capabilities
	$jsonRobotState = sdk_neato_doAction('getRobotState');
    $robotState = sdk_json_decode($jsonRobotState);
	$houseCleaning =  $robotState['availableServices']['houseCleaning'];

	switch ($houseCleaning) {
		case "minimal-1":
		case "minimal-2":
			$params = '{"category":2,"navigationMode":2}';
			break;
		case "basic-1":
			$params = '{"category":2,"mode":2,"modifier":1}';
			break;
		case "basic-2":
			$params = '{"category":2,"mode":2,"modifier":1,"navigationMode":2}';
			break;
		case "basic-3":
		case "basic-4":
			$params = '{"category":2,"mode":2,"navigationMode":2}';
			break;
	}
}

sdk_header('text/xml');
echo jsonToXML(sdk_neato_doAction($command, $params));