<?php
// Query all exceede units

// Function to handle ping
function ping($host, $timeout = 1) {
  $package = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
  $socket  = socket_create(AF_INET, SOCK_RAW, 1);
  socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
  socket_connect($socket, $host, null);

  $ts = microtime(true);
  socket_send($socket, $package, strLen($package), 0);
  if (socket_read($socket, 255))
    $result = microtime(true) - $ts;
  else
    $result = false;
  socket_close($socket);
  return $result;
  };

// Function to do 10 pings to given host and return status code
function statusping($host) {
  $pingtime = 0;
  $avgpingtime = 0;
  $packetloss = 0;

  for($i=1;$i<=10;$i++) {
    $pingtime = ping($host,2);
    if ($pingtime==FALSE)
      $packetloss++;
    else
      $avgpingtime += $pingtime;
    };

  if ($packetloss==10)
    $avgpingtime = 0;
  else
    $avgpingtime = round(($avgpingtime / (10-$packetloss))*1000);

  if ($packetloss>5)
    $status = 40002;
  else if ($packetloss>2 || $avgpingtime>1000)
    $status = 40001;
  else
    $status = 40000;

  return($status);
  };

// Connect to GTS Database
$link = mysql_connect('localhost','root','d@t@c0m#-db@s3');
if (!$link) {
  die("Cannot connect to GTS db");
};

// Select the GTS Database
if (!mysql_select_db('gts')) {
  die("Cannot select GTS db");
};

// Query Wild Blue Devices from GTS Database
$res = mysql_query("SELECT deviceID,ipAddressCurrent FROM Device WHERE isActive=1 AND deviceID like 'WB%';");

if (!$res) {
  die("Error cannot select devices");
};

// Build Device Status Insert Array
$index=0;

// Update timestamp on wildblue locations to stop it falling off the end during cleanup
$insertquery[$index] = "UPDATE EventData SET timestamp=".time()." WHERE statusCode=61472 and deviceID like 'WB%';";
$index++;

while ($ar = mysql_fetch_array($res, MYSQL_BOTH)) {
  $curstat = statusping($ar['ipAddressCurrent']);
  $insertquery[$index] = "REPLACE INTO EventData SET accountID='gtg',deviceID='".$ar['deviceID']."',timestamp=".time().",statusCode=".$curstat.";";
  $index++;
  $insertquery[$index] = "UPDATE Device SET lastInputState=".$curstat." WHERE deviceID='".$ar['deviceID']."';";
  $index++;
};

// Perform Status and Location inserts
foreach($insertquery as $querytext)
  {
  $res = mysql_query($querytext);
  };

// Free GTS database connection
mysql_close($link);

?>
