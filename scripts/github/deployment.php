<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

require __DIR__ . '/../../vendor/autoload.php';

$lagoonGitSha = getenv('LAGOON_GIT_SHA') ?: '';
if ($lagoonGitSha === '') {
  print 'Cannot detect LAGOON_GIT_SHA';
  exit(1);
}
$githubToken = getenv('GITHUB_TOKEN') ?: '';
if ($githubToken === '') {
  print 'Cannot detect GITHUB_TOKEN';
  exit(1);
}

$lagoonEnvironment = getenv('LAGOON_ENVIRONMENT');

$client = new Client([
  'base_uri' => 'https://api.github.com',
  'headers' => [
    'Authorization' => "token $githubToken",
  ],
]);

$createDeploymentBody = [
  'ref' => $lagoonGitSha,
  'environment' => $lagoonEnvironment,
  'transient_environment' => !empty(getenv('LAGOON_PR_NUMBER')),
  'production_environment' => getenv('LAGOON_ENVIRONMENT_TYPE') === 'production',
  'description' => $lagoonEnvironment,
];
print "Create deployment body: " . PHP_EOL;
var_export($createDeploymentBody);
print PHP_EOL;

$createDeploymentResponse = $client->post('/repos/simplytestme/website/deployments', [
  RequestOptions::JSON => $createDeploymentBody,
]);
$deploymentData = \json_decode((string) $createDeploymentResponse->getBody());
$deploymentId = $deploymentData->id;

$createDeploymentStatusBody = [
  'state' => 'success',
  'environment' => $lagoonEnvironment,
  'log_url' => 'https://dashboard.amazeeio.cloud/projects/simplytest/simplytest-' . $lagoonEnvironment . '/deployments/',
  'environment_url' => getenv('LAGOON_ROUTE'),
];
print "Create deployment body: " . PHP_EOL;
var_export($createDeploymentStatusBody);
print PHP_EOL;

$client->post("/repos/simplytestme/website/deployments/{$deploymentId}/statuses", [
  RequestOptions::JSON => $createDeploymentStatusBody,
]);
