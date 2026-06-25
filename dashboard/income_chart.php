<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified for MikhPay Dashboard Overhaul.
 */
session_start();
// hide all error
error_reporting(0);

header('Content-Type: application/json');

if (!isset($_SESSION["mikhmon"])) {
  die(json_encode(['error' => 'Unauthorized session']));
}

$session = $_GET['session'];

// load config
include('../include/config.php');
include('../include/readcfg.php');

// routeros api
include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');

$API = new RouterosAPI();
$API->debug = false;

if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
  die(json_encode(['error' => 'Failed to connect to RouterOS']));
}

// Get lists of last 12 months (e.g. jun2026)
$months = [];
for ($i = 11; $i >= 0; $i--) {
  $time = strtotime("-$i months");
  $mCode = strtolower(date("M", $time)) . date("Y", $time);
  $mLabel = date("M Y", $time);
  $months[$mCode] = [
    'label' => $mLabel,
    'income' => 0,
    'vouchers' => 0
  ];
}

// Fetch all scripts once and categorize in PHP (reduces API roundtrips from 12 to 1)
$getSR = $API->comm("/system/script/print");

if (is_array($getSR)) {
  foreach ($getSR as $row) {
    $owner = strtolower($row['owner']);
    if (isset($months[$owner])) {
      $parts = explode("-|-", $row['name']);
      if (count($parts) > 3) {
        $months[$owner]['income'] += (int)$parts[3];
        $months[$owner]['vouchers']++;
      }
    }
  }
}

// Format response data structure
$response = [
  'labels' => [],
  'income' => [],
  'vouchers' => []
];

foreach ($months as $mCode => $data) {
  $response['labels'][] = $data['label'];
  $response['income'][] = $data['income'];
  $response['vouchers'][] = $data['vouchers'];
}

echo json_encode($response);
?>
