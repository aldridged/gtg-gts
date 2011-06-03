<?php

// Take ascii block definitions and changes to geozone insert statements

// Read the data and coordinate files into arrays
echo "Opening data files...\n";
$datafile = file('blocks.data');
$coordfile = file('blocks.gen');

// Loop through the data and extract the ID and block names
echo "Extracting block names...\n";
foreach ($datafile as $line_num => $line) {
  if($line_num % 10 == 0) $namekey=trim($line);
  if($line_num % 10 == 6) $insertdata[$namekey]['blockname']=trim(trim($line),'"');
};

// Loop through the coordinates
echo "Extracting coordinates...\n";
$coordkey = 1;
foreach ($coordfile as $line_num => $line) {
  $contents = preg_split("/[\s]+/",$line);
  if(count($contents)==5) {
    $coordkey = 2;
    $namekey=trim($contents[1]);
    $lat=substr(trim($contents[3]),2,2).".".substr(trim($contents[3]),4,13);
    $long="-".substr(trim($contents[2]),3,2).".".substr(trim($contents[2]),5,13);
    $insertdata[$namekey]['lat1']=$lat;
    $insertdata[$namekey]['long1']=$long;
  }
  else if (count($contents)==4) {
    $latkey = "lat".$coordkey;
    $longkey = "long".$coordkey;
    $lat=substr(trim($contents[2]),2,2).".".substr(trim($contents[2]),4,13);
    $long="-".substr(trim($contents[1]),3,2).".".substr(trim($contents[1]),5,13);
    $insertdata[$namekey][$latkey]=$lat;
    $insertdata[$namekey][$longkey]=$long;
    $coordkey++;
  }
  else {
    $insertdata[$namekey]['count']=($coordkey-1);
  };
};

//Create the insert query
echo "Creating insert query...\n";
$index=1;
foreach ($insertdata as $blockrecord) {
  $coordindex = 1;
  $insertquery[$index] = "REPLACE INTO Geozone SET accountID='gtg',geozoneID='".$blockrecord['blockname']."',reverseGeocode=1,arrivalZone=1,departureZone=1,zoneType=3,displayName='".$blockrecord['blockname']."',description='".$blockrecord['blockname']."'";
  while ($coordindex <= $blockrecord['count']) {
    $insertquery[$index] .= ",latitude".$coordindex."=".$blockrecord['lat'.$coordindex].",longitude".$coordindex."=".$blockrecord['long'.$coordindex];
    $coordindex++;
  };
  $insertquery[$index] .= ",lastUpdateTime=".time().",creationTime=".time().";";
  $index++;
};
    
//Insert data into GTS database
echo "Inserting geozones into GTS database...\n";

//Connect to GTS Database
$link = mysql_connect('localhost','root','d@t@c0m#-db@s3');
if (!$link) {
  die("Cannot connect to GTS db");
};

// Select the GTS Database
if (!mysql_select_db('gts')) {
  die("Cannot select GTS db");
};

//Perform Geozone Inserts
foreach($insertquery as $querytext)
  {
  $res = mysql_query($querytext);
  };

//Free GTS database connection
mysql_close($link);

//Done
echo "Geozone insert complete.\n";
?>
