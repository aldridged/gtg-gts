<?php
// Query all broadband wireless units

// Load Mikrotik API Class
require('/usr/local/gts/routeros_api.class.php');

// Function to get availability over period samples
function Avail($devid,$period) {
  $res = mysql_query("select ROUND(AVG(100-(substring(rawData,locate(':',rawData,10)+1,(locate('%',rawData)-locate(':',rawData,10)-1)))),2) as AV from EventData where accountID='gtg' and deviceID='".$devid."' and rawData is not null order by timestamp DESC limit ".$period.";");

  if (!$res) {
    die("Error cannot get availability information");
  };

  $ar = mysql_fetch_array($res, MYSQL_BOTH);
  return($ar['AV']);
  };

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

// Function to do 10 pings to given host and return status code, latency and packetloss
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
  else if ($packetloss>2 || $avgpingtime>250)
    $status = 40001;
  else
    $status = 40000;
    
  $packetloss *= 10; 

  return(array($status,$avgpingtime,$packetloss));
  };
  
// Function to query Mikrotiks for current public IP address
function mtPublicIP($host) {

  // Instanciate MT API call
  $API = new routeros_api();
  $API->debug = false;
  
  // Set a default value to return if connection to MT fails
  $ipaddr = "0.0.0.0";

  // Query Mikrotik
  if ($API->connect($host, 'admin', 'DataCom')) {
    $API->write('/ip/dhcp-client/print');
    $ARRAY = $API->read();
    $API->disconnect();

    $ipaddr = substr($ARRAY['0']['address'],0,-3);

    if ($ipaddr=="") {
      $API->connect($host, 'admin', 'DataCom');
      $API->write('/ip/address/print');
      $ARRAY = $API->read();
      $API->disconnect();
    
      foreach($ARRAY as $addr) {
        if ($addr['interface']=="ether1") {
          $ipaddr = substr($addr['address'],0,-3);
          };
        };
      };
    };

  return($ipaddr);
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

// Query Broadband Devices from GTS Database
$res = mysql_query("SELECT deviceID,ipAddressCurrent FROM Device WHERE isActive=1 AND deviceID like 'BBW%';");

if (!$res) {
  die("Error cannot select devices");
};

// Build Device Status Insert Array
$index=0;

// Update timestamp on broadband locations to stop it falling off the end during cleanup
$insertquery[$index] = "UPDATE EventData SET timestamp=".time()." WHERE statusCode=61472 and deviceID like 'BBW%';";
$index++;

// Loop though devices getting status information and building inserts
while ($ar = mysql_fetch_array($res, MYSQL_BOTH)) {
  list($curstat,$latency,$pl) = statusping($ar['ipAddressCurrent']);
  $pubip = mtPublicIP($ar['ipAddressCurrent']);
  $avail24h = Avail($ar['deviceID'],144);
  $avail7d = Avail($ar['deviceID'],1008);
  $avail30d = Avail($ar['deviceID'],4320);
  $insertquery[$index] = "REPLACE INTO EventData SET accountID='gtg',deviceID='".$ar['deviceID']."',timestamp=".time().",statusCode=".$curstat.",rawData='Latency:".$latency." Packet Loss:".$pl."% Public IP:".$pubip."';";
  $index++;
  $insertquery[$index] = "UPDATE Device SET lastInputState=".$curstat.",lastRtt=".$latency.",notes='<br>24 Hour Availability: ".$avail24h."%<br>7 Day Availability: ".$avail7d."%<br>30 Day Availability: ".$avail30d."%<br>Packet Loss: ".$pl."%<br>Current Public IP: ".$pubip."<br>' WHERE deviceID='".$ar['deviceID']."';";
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
