<?php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set("Asia/Kuala_Lumpur");

$respObject = [];
$respObject["ok"] = false;

if (!isset($_GET['rand'])) {
    $respObject["message"] = "rand not specified.";
    exit(json_encode($respObject));
}

if (!isset($_GET['coord'])) {
    $respObject["message"] = "coord not specified.";
    exit(json_encode($respObject));
}

$lang = "ms";
if (isset($_GET['lang'])) {
    $allowed_lang = ['ms', 'en'];
    if (in_array($_GET['lang'], $allowed_lang)) {
        $lang = $_GET['lang'];
    }
}

$coord = $_GET['coord'];
// $coord = "3.1385036,101.6169494";
$coordSplit = explode(",", $coord);

if (count($coordSplit) !== 2) {
    $respObject["message"] = "Invalid coord. Make sure it is in format lat,long";
    exit(json_encode($respObject));
}

$curlContextOpts = [
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ],
];
$mptAPI = "https://mpt.i906.my/api/prayer/$coord";
$mptResp = @file_get_contents($mptAPI, false, stream_context_create($curlContextOpts));

if (!$mptResp) {
    $respObject["message"] = "404 Not Found.";
    exit(json_encode($respObject));
}

$mptResp = json_decode($mptResp);

$mptData = $mptResp->data;
$userPlace = $mptData->place;
$times = $mptData->times;

## Get current time in UNIX
$currentTime = time();

## Get today index
$today = intval(date('d'));
$todayIndex = $today - 1;

## Get today prayer times
$todayPrayerTimes = $times[$todayIndex];

## Remove Syuruk time
array_splice($todayPrayerTimes, 1, 1);

## Checking for the next prayer times
$nextPrayerTime = 0;                ## Times
$nextPrayerTimeName = "Subuh";      ## Prayer Times Name
$day = "Today";                     ## When

for ($i = 0; $i < 5; $i++) {

    ## Subuh checking (first)
    if ($currentTime < $todayPrayerTimes[0]) {
        $nextPrayerTime = $todayPrayerTimes[0]; ## Subuh
        break;
    }

    ## Isyak checking (last)
    if ($currentTime > $todayPrayerTimes[4]) {
        ## After isyak, we set to tomorrow subuh
        $day = "Tomorrow";

        $tomorrowPrayerTimes = $times[$todayIndex + 1];
        $nextPrayerTime = $tomorrowPrayerTimes[0];
        break;
    }

    if ($i > 0) {
        if ($currentTime >= $todayPrayerTimes[$i - 1] && $currentTime < $todayPrayerTimes[$i]) {
            $nextPrayerTime = $todayPrayerTimes[$i];

            switch ($i) {
                case 0:
                    $nextPrayerTimeName = "Subuh";
                    break;
                case 1:
                    $nextPrayerTimeName = "Zohor";
                    break;
                case 2:
                    $nextPrayerTimeName = "Asar";
                    break;
                case 3:
                    $nextPrayerTimeName = "Maghrib";
                    break;
                case 4:
                    $nextPrayerTimeName = "Isyak";
                    if ($lang === "en") {
                        $nextPrayerTimeName = "Isha'";
                    }
                    break;
            }

            break;
        }
    }
}

$allTimes = [];
for ($i=0; $i < count($todayPrayerTimes); $i++) {
    array_push($allTimes, date('g:iA', $todayPrayerTimes[$i]));
}

$respObject["ok"] = true;
$respObject["day"] = $day;
$respObject["nextPrayerTimeName"] = $nextPrayerTimeName;
$respObject["nextPrayerTimeDate"] = date('d M Y', $nextPrayerTime);
$respObject["nextPrayerTime"] = date('g:iA', $nextPrayerTime);
$respObject["place"] = $userPlace;

$respObject["allTimesToday"] = $allTimes;

$spokenText = "";
if ($lang === "ms") {
    if ($day === "Today") {
        $spokenText = "Waktu solat seterusnya ialah $nextPrayerTimeName pada pukul " . $respObject["nextPrayerTime"];
    } else {
        $spokenText = "Waktu subuh esok hari ialah pada pukul " . $respObject["nextPrayerTime"];
    }
} else if ($lang === "en") {
    if ($day === "Today") {
        $spokenText = "The next prayer times is $nextPrayerTimeName at " . $respObject["nextPrayerTime"];
    } else {
        $spokenText = "Fajr tomorrow is at " . $respObject["nextPrayerTime"];
    }
}
$respObject["spokenText"] = $spokenText;

exit(json_encode($respObject));