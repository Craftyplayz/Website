<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';

// Styling for better readability
echo '<style>
    h1 { font-size: 2em; color: #333; }
    h2 { font-size: 1.5em; color: #555; margin-top: 20px; }
    .info, .orders { margin-left: 20px; }
    .info p, .orders p { font-size: 1.1em; line-height: 1.5; }
    .info { margin-top: 10px; }
    .orders { margin-top: 20px; }
    .info, .orders { background-color: #f8f8f8; padding: 10px; border-radius: 8px; }
</style>';

$profileUrl = 'https://api.hypixel.net/v2/skyblock/profile?key=' . $hypixelkeyapp . '&profile=c468bc63-04f8-4dbd-ac21-3f4efb878cc1';
$playerdata = json_decode(file_get_contents($profileUrl), true);
$bazaarurl = 'https://api.hypixel.net/v2/skyblock/bazaar';
$bazaardata = json_decode(file_get_contents($bazaarurl), true);

if (!$playerdata || !$bazaardata) {
    die('<p>Failed to retrieve data from the API.</p>');
}

$coinpurse = 0;
if (isset($playerdata['profile']['members']) && is_array($playerdata['profile']['members'])) {
    $memberKeys = array_keys($playerdata['profile']['members']);
    if (isset($playerdata['profile']['members']['22159541fde841e6a104593e1cb3a456']['currencies']['coin_purse'])) {
        $coinpurse = $playerdata['profile']['members']['22159541fde841e6a104593e1cb3a456']['currencies']['coin_purse'];
    }
}


echo '<h1>Ghast Tear Bazaar Calculation</h1>';
echo '<h2>Balance</h2>';
echo '<div class="info"><p>Current Coin Purse: ' . number_format($coinpurse) . '</p></div>';

$pricePerUnit = 0;
if (isset($bazaardata['products']['ENCHANTED_GHAST_TEAR']['sell_summary'][0]['pricePerUnit'])) {
    $pricePerUnit = $bazaardata['products']['ENCHANTED_GHAST_TEAR']['sell_summary'][0]['pricePerUnit'];
}

echo '<h2>Price Per Unit</h2>';
echo '<div class="info"><p>Price per unit: ' . number_format($pricePerUnit, 2) . '</p></div>';

$usableFunds = max(0, $coinpurse - 1000000);
$maxItems = ($pricePerUnit > 0) ? intdiv($usableFunds, $pricePerUnit) : 0;
$maxPerOrder = 71680;
$totalItemsOrdered = 0; // Initialize total items ordered
$revenuePerItem = 1163.125; // Revenue per item ordered
$totalProfit = 0; // Initialize total profit

echo '<h2>Order Summary</h2>';
echo '<div class="orders">';
while ($maxItems > 0) {
    $itemsToOrder = min($maxItems, $maxPerOrder);
    echo '<p>Items to order: ' . $itemsToOrder . '</p>';
    $totalItemsOrdered += $itemsToOrder; // Add to total items ordered
    $maxItems -= $itemsToOrder;

    // Calculate profit for this batch of items
    $costPerItem = $pricePerUnit; // Cost per item
    $profitPerItem = $revenuePerItem - $costPerItem; // Profit per item
    $totalProfit += $profitPerItem * $itemsToOrder; // Add profit for this batch to total profit
}
echo '</div>';

echo '<h2>Total Items Ordered</h2>';
echo '<div class="info"><p>Total Items Ordered: ' . $totalItemsOrdered . '</p></div>';

echo '<h2>Estimated Total Profit</h2>';
echo '<div class="info"><p>Estimated Total Profit: ' . number_format($totalProfit, 2) . '</p></div>';

include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>
