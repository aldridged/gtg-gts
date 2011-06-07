<?php
// Query KVH units from KML file

// Connect and retrieve KML data
$ch = curl_init('http://208.83.165.114/KMLs/BDA44F00-6C56-4337-819D-DF4E8D6DE462/p45.kml');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10(KHTML, like Gecko) Chrome/8.0.552.237 Safari/534.10');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, 'datacom:#ebbflow!');
curl_setopt($ch, CURLOPT_PORT, 8080);
$result = curl_exec($ch);

// Convert returned XML to array
$xml = simplexml_load_string($result);

// Parse returned XML
$index=0;
foreach($xml->Folder->Placemark as $data) {
  $cleancoords=explode(",",$data->Point->coordinates);
  $cleanname=explode(" ",$data->name);
  $cleandesc=explode("\n",$data->description);
  $cleanstatus=trim(sprintf("%s",$data->description->p));
  $statuscode=explode(" ",$cleanstatus);
  $cleanid=explode(" ",$cleandesc[11]);
  $cleanspeed=explode(" ",$cleandesc[10]);
  $kvhdata[$index]['name']=$cleanname[3];
  $kvhdata[$index]['latitude']=$cleancoords[1];
  $kvhdata[$index]['longitude']=$cleancoords[0];
  $kvhdata[$index]['speed']=$cleanspeed[3]*1.852;
  $kvhdata[$index]['ipaddr']=$cleanname[0];
  $kvhdata[$index]['id']=$cleanid[2];
  $kvhdata[$index]['status']=$cleanstatus;
  if($statuscode[4]=='In') { 
    $kvhdata[$index]['statuscode']="40000";
  } else $kvhdata[$index]['statuscode']="39999";
  $kvhdata[$index]['notes']=$cleandesc[3]."<br />".$cleandesc[5]."<br />".$cleandesc[6];
  $index++;
};

//Build Device and Location Insert Query
$index=0;
foreach($kvhdata as $data) {
  $insertquery[$index] = "INSERT INTO Device (accountID,deviceID,groupID,equipmentType,vehicleID,uniqueID,displayName,description,isActive,lastUpdateTime,lastInputState,notes) VALUES ('gtg','".$data['id']."','kvh','netmodem','".$data['name']."','".$data['id']."','".$data['name']."','".$data['name']."',1,".time().",".$data['statuscode'].",'".$data['status']."<br />".$data['notes']."') ON DUPLICATE KEY UPDATE groupID=VALUES(groupID),lastUpdateTime=VALUES(lastUpdateTime),displayName=VALUES(displayName),description=VALUES(description),lastInputState=VALUES(lastInputState),notes=VALUES(notes);";
  $index++;
  $insertquery[$index] = "REPLACE INTO EventData SET accountID='gtg',deviceID=".$data['id'].",timestamp=".time().",statusCode=61472,latitude=".$data['latitude'].",longitude=".$data['longitude'].",speedKPH=".$data['speed'].";";
  $index++;
};

//Connect to GTS Database
$link = mysql_connect('localhost','root','d@t@c0m#-db@s3');
if (!$link) {
  die("Cannot connect to GTS db");
};

// Select the GTS Database
if (!mysql_select_db('gts')) {
  die("Cannot select GTS db");
};

//Perform Device and location Inserts
foreach($insertquery as $querytext)
  {
  $res = mysql_query($querytext);
  };

//Free GTS database connection
mysql_close($link);
?>
