<?php declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;

require __DIR__ . '/vendor/autoload.php';

// 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
$client = new \GuzzleHttp\Client([
  'auth' => [
    'mglaman',
    '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',
  ],
  'base_uri' => 'https://api.github.com',
  'headers' => [
    'Accept' => 'application/vnd.github.v3+json',
  ],
]);

while (true) {
  try {
    $uri = '/repos/nerdstein/simplytest-instances/branches?per_page=100';
    $response = $client->get($uri);
    $branches = array_map(static function (array $branch) {
      return $branch['name'];
    }, \json_decode((string) $response->getBody(), TRUE, JSON_PRETTY_PRINT, JSON_THROW_ON_ERROR));
    foreach ($branches as $branch) {
      if ($branch === 'master') {
        continue;
      }
      if (str_starts_with($branch, 'base-')) {
        continue;
      }
      print "Deleting $branch" . PHP_EOL;
      $client->delete('/repos/nerdstein/simplytest-instances/git/refs/heads/' . $branch);
      usleep(500);
    }
  } catch (ClientException $e) {
    var_export($e->getResponse()->getHeaders());
    var_export((string) $e->getResponse()->getBody());
    break;
  }
}

/*
$page = 1;
$branches = [];
while ($page <= 15) {
  try {
    $uri = '/repos/nerdstein/simplytest-instances/branches?per_page=100&page=' . $page;
    print "Fetching $uri" . PHP_EOL;
    $response = $client->get($uri);
    $branches[] = array_map(static function (array $branch) {
      return $branch['name'];
    }, \json_decode((string) $response->getBody(), TRUE, JSON_PRETTY_PRINT, JSON_THROW_ON_ERROR));
    $page++;
  } catch (\Throwable $e) {
    break;
  }
}
$branches = array_merge(...$branches);
//var_export($branches);

foreach ($branches as $branch) {
  if ($branch === 'master') {
    continue;
  }
  if (str_starts_with($branch, 'base-')) {
    continue;
  }
  print "Deleting $branch" . PHP_EOL;
  try {
    $client->delete('/repos/nerdstein/simplytest-instances/git/refs/heads/' . $branch);
    usleep(100);
  } catch (\GuzzleHttp\Exception\ClientException $e) {
    print "Couldn't delete $branch: " . $e->getResponse()->getBody() . PHP_EOL;
    break;
  }
}
*/
