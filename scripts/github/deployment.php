<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

require __DIR__ . '/../../vendor/autoload.php';

$lagoonGitSha = $_ENV['LAGOON_GIT_SHA'] ?? '';
if ($lagoonGitSha === '') {
  print 'Cannot detect LAGOON_GIT_SHA';
  exit(1);
}
$githubToken = $_ENV['GITHUB_TOKEN'] ?? '';
if ($githubToken === '') {
  print 'Cannot detect GITHUB_TOKEN';
  exit(1);
}

$client = new Client([
  'base_uri' => 'https://api.github.com',
  'headers' => [
    'Authorization' => "token $githubToken",
  ],
]);

$createDeploymentResponse = $client->post('/repos/simplytestme/website/deployments', [
  RequestOptions::JSON => [
    'ref' => $lagoonGitSha,
    'environment' => $_ENV['LAGOON_ENVIRONMENT'],
    'transient_environment' => !empty($_ENV['LAGOON_PR_NUMBER']),
    'production_environment' => $_ENV['LAGOON_ENVIRONMENT_TYPE'] === 'production',
    'description' => $_ENV['LAGOON_ENVIRONMENT']
  ],
]);
$deploymentData = \json_decode((string) $createDeploymentResponse->getBody());
$deploymentId = $deploymentData->id;

$client->post("/repos/simplytestme/website/deployments/{$deploymentId}/statuses", [
  RequestOptions::JSON => [
    'state' => 'success',
    'environment' => $_ENV['LAGOON_ENVIRONMENT'],
    'log_url' => 'https://dashboard.amazeeio.cloud/projects/simplytest/simplytest-' . $_ENV['LAGOON_ENVIRONMENT'] . '/deployments/',
    'environment_url' => $_ENV['LAGOON_ROUTE'],
  ],
]);
