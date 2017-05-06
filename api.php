<?php
require_once __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
	throw new Exception('This application must be run on the command line.');
}

try {
	$client = createClient();

	$service = new Google_Service_Script($client);

	$function = $argv[1];
	$score = isset($argv[2]) ? $argv[2] : null;
	$request = createRequest($function, $score);

	$operation = $service->scripts->run('Mymnztob-F5LmcHT0BqHVqyZNcORKOCcz', $request);

	echo formatApiResponse($operation);

} catch (Exception $e) {
	// The API encountered a problem before the script started executing.
	echo 'Caught exception: ', $e->getMessage(), "\n";
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 * @throws Exception
 */
function createClient() {
	$client = authenticateAClient();
	$attempts = 1;

	while(!$client && $attempts < 5) {
		$client = authenticateAClient();
		$attempts++;
	}

	if (!$client) {
		throw new Exception('Could not authorize client after 5 attempts');
	}

	return $client;
}

/**
 * @return Google_Client|null
 */
function authenticateAClient() {
	$client = new Google_Client();
	$client->setApplicationName('MusicPractice');
	$client->setScopes(["https://www.googleapis.com/auth/spreadsheets"]);
	$client->setAuthConfig(__DIR__ . '/client_secret.json');

	// https://developers.google.com/identity/protocols/OAuth2WebServer#offline
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

		return null;
	}

	try {
		$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
	} catch (LogicException $e) {
		if ('refresh token must be passed in or set as part of setAccessToken' == $e->getMessage()) {
			unlink($credentialsPath);

			return null;
		}
	}

	file_put_contents($credentialsPath, json_encode($client->getAccessToken()));

	return $client;
}

/**
 * @param string $function API method
 * @param string|null $score
 * @return Google_Service_Script_ExecutionRequest
 */
function createRequest($function, $score = null) {
	$request = new Google_Service_Script_ExecutionRequest();
	$request->setFunction($function);
	$request->setDevMode(true);
	if (isset($score)) {
		$request->setParameters([
			'score' => $score
		]);
	}

	return $request;
}

function formatApiResponse(Google_Service_Script_Operation $operation) {
	$out = "";

	if ($operation->getError()) {
		// Extract the first (and only) set of error details. The values of this
		// object are the script's 'errorMessage' and 'errorType', and an array of
		// stack trace elements.
		$error = $operation->getError()['details'][0];
		$out .= sprintf("Script error message: %s\n", $error['errorMessage']);

		if (array_key_exists('scriptStackTraceElements', $error)) {
			// There may not be a stacktrace if the script didn't start executing.
			$out .= "Script error stacktrace:\n";
			foreach ($error['scriptStackTraceElements'] as $trace) {
				$out .= sprintf("\t%s: %d\n", $trace['function'], $trace['lineNumber']);
			}
		}
	} else {
		// The structure of the result depends upon what the Apps Script returns
		$resp = $operation->getResponse();
		$result = $resp['result'];
		$out .= json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
	}

	return $out;
}
