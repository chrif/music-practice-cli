<?php
require_once __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
	throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
	$client = new Google_Client();
	$client->setApplicationName('MusicPractice');
	$client->setScopes(array(
			"https://www.googleapis.com/auth/spreadsheets")
	);
	$client->setAuthConfig(__DIR__ . '/client_secret.json');
	$client->setAccessType('offline');

	// Load previously authorized credentials from a file.
	$credentialsPath = __DIR__ . '/credentials.json';
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
		if (!file_exists(dirname($credentialsPath))) {
			mkdir(dirname($credentialsPath), 0700, true);
		}
		file_put_contents($credentialsPath, json_encode($accessToken));
		printf("Credentials saved to %s\n", $credentialsPath);
	}

	try {
		$client->setAccessToken($accessToken);
	} catch (InvalidArgumentException $e) {
		unlink($credentialsPath);

		return getClient();
	}

	try {
		$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
	} catch (LogicException $e) {
		if ('refresh token must be passed in or set as part of setAccessToken' == $e->getMessage()) {
			unlink($credentialsPath);

			return getClient();
		}
	}
	file_put_contents($credentialsPath, json_encode($client->getAccessToken()));

	return $client;
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Script($client);

$scriptId = 'Mymnztob-F5LmcHT0BqHVqyZNcORKOCcz';

// Create an execution request object.
$request = new Google_Service_Script_ExecutionRequest();
$request->setFunction($argv[1]);
$request->setDevMode(true);
if (isset($argv[2])) {
	$request->setParameters([
		'score' => $argv[2]
	]);
}

try {
	// Make the API request.
	$response = $service->scripts->run($scriptId, $request);

	if ($response->getError()) {
		// The API executed, but the script returned an error.

		// Extract the first (and only) set of error details. The values of this
		// object are the script's 'errorMessage' and 'errorType', and an array of
		// stack trace elements.
		$error = $response->getError()['details'][0];
		printf("Script error message: %s\n", $error['errorMessage']);

		if (array_key_exists('scriptStackTraceElements', $error)) {
			// There may not be a stacktrace if the script didn't start executing.
			print "Script error stacktrace:\n";
			foreach ($error['scriptStackTraceElements'] as $trace) {
				printf("\t%s: %d\n", $trace['function'], $trace['lineNumber']);
			}
		}
	} else {
		// The structure of the result will depend upon what the Apps Script
		// function returns. Here, the function returns an Apps Script Object
		// with String keys and values, and so the result is treated as a
		// PHP array (folderSet).
		$resp = $response->getResponse();
		$result = $resp['result'];
		echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
	}
} catch (Exception $e) {
	// The API encountered a problem before the script started executing.
	echo 'Caught exception: ', $e->getMessage(), "\n";
}