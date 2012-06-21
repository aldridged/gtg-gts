<?php
/* Datapump - Devices from NMS to GTS (SNMP Version) */
/* Added Geozone support for bordelon and LMbotruc */

// Function to handle inserting Geozone arrival/departure codes
function handleGeozone($link,$devid,$lat,$long) {
  // Find previous geozone events for devid
  $res = mysql_query("SELECT geozoneID,statusCode,address FROM EventData WHERE (deviceID='".$devid."' AND (statusCode=61968 OR statusCode=62000) AND geozoneID IS NOT NULL) ORDER BY timestamp DESC LIMIT 1",$link);

  // If there are events
  if(mysql_num_rows($res)>0) {
    list($geozoneID,$statusCode,$oldaddr) = mysql_fetch_row($res);

    // If the previous event is an arrival
    if($statusCode==61968) {
      $res = mysql_query("SELECT geozoneID,displayName FROM Geozone WHERE (minLatitude<=".$lat." AND maxLatitude>=".$lat." AND minLongitude<=".$long." AND maxLongitude>=".$long.") LIMIT 1",$link);
      if(mysql_num_rows($res)>0) {
        list($newgeozoneID,$geodesc) = mysql_fetch_row($res);
        if($newgeozoneID<>$geozoneID) { 
          // Insert departure from previous and arrival in current
          $insert = "INSERT INTO EventData (accountID,deviceID,geozoneID,statusCode,address,timestamp) VALUES (";
          $insert .= "'gtg','".$devid."','".$geozoneID."',62000,'".$oldaddr."',".time()."),(";
          $insert .= "'gtg','".$devid."','".$newgeozoneID."',61968,'".$geodesc."',".(time()+10).")";
          $res = mysql_query($insert,$link);
          }
        } else {
        // Insert departure for current geozone
	      $insert = "INSERT INTO EventData (accountID,deviceID,geozoneID,statusCode,address,timestamp) VALUES ('gtg','".$devid."','".$geozoneID."',62000,'".$oldaddr."',".time().")";
	      $res = mysql_query($insert,$link);
        }
      }  else {
      // Last event was a departure so insert arrival information
      $res = mysql_query("SELECT geozoneID,displayName FROM Geozone WHERE (minLatitude<=".$lat." AND maxLatitude>=".$lat." AND minLongitude<=".$long." AND maxLongitude>=".$long.") LIMIT 1",$link);
      if(mysql_num_rows($res)>0) {
        list($newgeozoneID,$geodesc) = mysql_fetch_row($res);
        $insert = "INSERT INTO EventData (accountID,deviceID,geozoneID,statusCode,address,timestamp) VALUES ('gtg','".$devid."','".$newgeozoneID."',61968,'".$geodesc."',".time().")";
	    $res = mysql_query($insert,$link);
        }
      }
    }  else {
      // First geozone event so insert arrival information
      $res = mysql_query("SELECT geozoneID,displayName FROM Geozone WHERE (minLatitude<=".$lat." AND maxLatitude>=".$lat." AND minLongitude<=".$long." AND maxLongitude>=".$long.") LIMIT 1",$link);
      if(mysql_num_rows($res)>0) {
        list($newgeozoneID,$geodesc) = mysql_fetch_row($res);
        $insert = "INSERT INTO EventData (accountID,deviceID,geozoneID,statusCode,address,timestamp) VALUES ('gtg','".$devid."','".$newgeozoneID."',61968,'".$geodesc."',".time().")";
	    $res = mysql_query($insert,$link);
        }
    };
}

// Read in mib
snmp_read_mib("/usr/share/snmp/mibs/IDIRECT-REMOTE-MIB.txt");

// SNMP Query NMS Server
$a = snmp2_real_walk("10.110.254.11", "dcsatnetwork", "1.3.6.1.4.1.13732");

// Convert returned data to usable array
foreach ($a as $idx=>$val) {
  list($branch,$snmpid) = explode(".",$idx);
  list($tree,$node) = explode("::",$branch);
  list($type,$value) = explode(":",$val,2);

  switch($node) {
    case 'nmstate': $netmodem[$snmpid][$node] = substr($value,(strpos($value,"(")+1),1);
                    break;

    default: $netmodem[$snmpid][$node] = trim($value);
  } 
}

// Open link to NMS database
$link = mysql_connect('10.110.254.11','noc','datacom');
if (!$link) {
  die("Cannot connect to NMS db");
};

// Select the NMS Database
if (!mysql_select_db('nms')) {
  die("Cannot select NMS db");
};

// Query Devices from NMS Database
$res = mysql_query("SELECT DID,NetModemId,ModemSn,NetModemName,IsMobile,LatDegrees,LatMinutes,LatSeconds,LongDegrees,LongMinutes,LongSeconds FROM NetModem,GeoLocation WHERE LocationID=GeoLocationID AND ActiveStatus=1;");

if (!$res) {
  die("Error cannot select devices");
};

// Cycle through devices filling in static GPS info if missing
while ($ar = mysql_fetch_array($res, MYSQL_BOTH)) {
  $lat = round($ar["LatDegrees"]+($ar["LatMinutes"]/60)+($ar["LatSeconds"]/3600),2);
  $long = round($ar["LongDegrees"]+($ar["LongMinutes"]/60)+($ar["LongSeconds"]/3600),2);
  if($netmodem[$ar["DID"]]["geoloc"]=="") $netmodem[$ar["DID"]]["geoloc"] = "LAT-LONG : [LAT = ".$lat."N LONG = ".$long."W]";
};

// Query Disabled Devices from NMS Database
$res = mysql_query("SELECT NetModemId FROM NetModem WHERE ActiveStatus=0;");

if (!$res) {
  die("Error cannot select disabled devices");
};

// Build Disabled Device Insert Array
$disabledinsertquery = "UPDATE Device SET lastInputState=39999 WHERE deviceID IN (";
while ($ar = mysql_fetch_array($res, MYSQL_BOTH)) {
  $disabledinsertquery .= $ar["NetModemId"].",";
};
$disabledinsertquery = substr($disabledinsertquery,0,-1).")";

// Free up query
mysql_free_result($res);

// Close connection to NMS DB
mysql_close($link);

//Connect to GTS Database
$link = mysql_connect('localhost','root','d@t@c0m#-db@s3');
if (!$link) {
  die("Cannot connect to GTS db");
};

// Select the GTS Database
if (!mysql_select_db('gts')) {
  die("Cannot select GTS db");
};

// Build Device Insert Query, GeoLoc Insert query

$deviceinsertquery = "INSERT INTO Device (accountID,deviceID,groupID,equipmentType,vehicleID,uniqueID,displayName,description,isActive,lastUpdateTime,lastInputState,ipAddressCurrent,lastReason,lastSnrCalDown,lastSnrCalUp,lastPowerDbm,lastRtt) VALUES ";

$geolocinsertquery = "REPLACE INTO EventData (accountID,deviceID,timestamp,statusCode,latitude,longitude) VALUES ";

$statusinsertquery = "REPLACE INTO EventData (accountID,deviceID,timestamp,statusCode,rawData) VALUES ";

foreach($netmodem as $nm) {
  if($nm['typeid']=="remote(3)") {
    $stati = array(40000,40001,40002,40002,40002);
    $gid=explode(" ",$nm['nmname']);
    $n = sscanf($nm['geoloc'], "LAT-LONG : [LAT = %fN LONG = %fW", $lat, $long);
    $long = 0-$long;
    $latency = $nm['latencyvalue'];
    if(($latency=="Request timed out")||($latency=="")) $latency=0;
    if($nm['downsnr']=="") $nm['downsnr']=0;
    if($nm['upsnr']=="") $nm['upsnr']=0;
    if($nm['txpower']=="") $nm['txpower']=0;

    if($nm['nmstate']>=2) {$reason = $nm['nmalarms'];} else {$reason = $nm['nmwarnings'];};
    
    if((stripos($gid[0],"Bordelon-")!==false)||($gid[0]=="L&M")) handleGeozone($link,$nm['nmid'],$lat,$long);

    $deviceinsertquery .= "('gtg',".$nm['nmid'].",'".$gid[0]."','netmodem','".$nm['netdid']."','".$nm['nmid']."','".$nm['nmname']."','".$nm['nmname']."',1,".time().",".$stati[$nm['nmstate']].",'".$nm['ethipadr']."','".$reason."',".$nm['downsnr'].",".$nm['upsnr'].",".$nm['txpower'].",".$latency."),";
    $geolocinsertquery .= "('gtg',".$nm['nmid'].",".time().",61472,".$lat.",".$long."),";
    $statusinsertquery .= "('gtg',".$nm['nmid'].",".time().",".$stati[$nm['nmstate']].",'".$reason."'),";
    };
  };

$deviceinsertquery = substr($deviceinsertquery,0,-1)." ON DUPLICATE KEY UPDATE groupID=VALUES(groupID),lastUpdateTime=VALUES(lastUpdateTime),displayName=VALUES(displayName),description=VALUES(description),lastInputState=VALUES(lastInputState),ipAddressCurrent=VALUES(ipAddressCurrent),lastReason=VALUES(lastReason),lastSnrCalDown=VALUES(lastSnrCalDown),lastSnrCalUp=VALUES(lastSnrCalUp),lastPowerDbm=VALUES(lastPowerDbm),lastRtt=VALUES(lastRtt)";

$geolocinsertquery = substr($geolocinsertquery,0,-1);

$statusinsertquery = substr($statusinsertquery,0,-1);

// Run Data Inserts
$res = mysql_query($deviceinsertquery);
$res = mysql_query($geolocinsertquery);
$res = mysql_query($statusinsertquery);
$res = mysql_query($disabledinsertquery);

// Clean Up
mysql_close($link);
?>
