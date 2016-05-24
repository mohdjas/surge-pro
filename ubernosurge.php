<?php
/*
 * The Lat-long of the source location. Change this to your location.
 */
$source = array("12.976633", "77.639888");
/*
 * The Lat-long of the destination. This is needed for the API.
 * You could put in any value here as long as it makes sense -
 * We are just finding out if Uber is surging at the source location.
 * You could ideally put in any location here, but if you are
 * going to put in a value which is in another country or state
 * or where Uber does not operate, it might not work.
 */
$destination = array("12.981934", "77.623241");
/*
 * The list of folks who are interested in getting a intimation when Uber
 * has no surge. Something like
 * $interestedFolks = array("999999999", "888888888");
 */
$interestedFolks = array();
/*
 * This is the Uber Server Token from https://developer.uber.com
 */
$uberToken = "";
/*
 * The Exotel Sid and Token from http://my.exotel.in/settings/site#api-settings
 */
$exotelSid = "";
$exotelToken = "";
/* 
 * The Id of the app you created here - http://my.exotel.in/Exotel/apps
 */
$appId = "";
/* 
 * The Exophone to call from
 */
$exotelVn = "";

function getCurlObj()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    return $ch;
}

function getRideData($from, $to = null)
{
    global $uberToken;
    $ch = getCurlObj();

    $baseUrl = "https://api.uber.com/v1/estimates/price";
    $parametersArray = array(
        "start_latitude" => $from[0],
        "start_longitude" => $from[1]
    );
    if (!is_null($to)) {
        $params["end_latitude"] = $to[0];
        $params["end_longitude"] = $to[1];
    }

    $url = $baseUrl . "?" . http_build_query($parametersArray);
    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "authorization: Token $uberToken"
        ));

    $res = curl_exec($ch);
    $error = curl_error($ch);
    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpStatusCode == 200) {
        $res = json_decode($res);
    } else {
        $res = $httpStatusCode;
    }
    curl_close($ch);

    return $res;
}

function isUberSurging($data)
{
    $surging = true;
    if (empty($data->prices)) {
        echo "No rides available!\n";
        return $surging;
    } else {
        foreach ($data->prices as $uberProduct) {
            if ($uberProduct->surge_multiplier == 1.0) {
                $surging = false;
            }
        }
    }
    return $surging;
}

function intimateSubscriber()
{
    global $exotelToken, $exotelSid, $appId;
    global $interestedFolks;
    foreach ($interestedFolks as $subscriberNum) {
        $postData = array(
            'From' => "$subscriberNum",
            'To' => "$exotelVn",
            'CallerId' => "$exotelVn",
            'Url' => "http://my.exotel.in/exoml/start/" . $appId,
            'CallType' => "trans"
            );
        $ch = getCurlObj();

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        curl_setopt($ch, CURLOPT_USERPWD, $exotelSid . ":" . $exotelToken);

        $url = "https://twilix.exotel.in/v1/Accounts/" . $exotelSid . "/Calls/connect";
        curl_setopt($ch, CURLOPT_URL, $url);

        $http_result = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
}


function main()
{
    global $source, $destination;
    $uberData = getRideData($source, $destination);

    if (!is_object($uberData)) {
        exit("Uber API did not respond as expected: response was $uberData.\n");
    }

    if (isUberSurging($uberData)) {
        echo ("Uber is surging or no rides are available - not calling anyone.\n");
    } else {
        echo ("No surge on this run. Intimating folks.\n");
        intimateSubscriber();
    }
}

main();
