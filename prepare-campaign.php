<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if ($argc !== 3) {
    fwrite(STDERR, 'Usage: php prepare-campaign.php CAMP-001 PROMO15' . PHP_EOL);
    exit(1);
}
$preparer = new Lab\CampaignPreparer((new Lab\Database())->connect());
echo 'New requests prepared: ' . $preparer->prepare($argv[1], $argv[2]) . PHP_EOL;
