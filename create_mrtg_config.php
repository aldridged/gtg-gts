<?php
/* Datapump - Devices from NMS to GTS (SNMP Version) */

error_reporting(E_ALL ^ E_NOTICE);

// Read in mib
snmp_read_mib("/usr/share/snmp/mibs/IDIRECT-REMOTE-MIB.txt");

// SNMP Query NMS Server
$a = snmp2_real_walk("204.9.216.250", "dcsatnetwork", 
"1.3.6.1.4.1.13732");

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
};

//print_r($netmodem);

foreach ($netmodem as $nm) {
  if($nm[typeid]=="remote(3)") {
    echo "Target[".$nm[nmid]."]: downstreamtotalKiloBytes.".$nm[nmdid]."&upstreamtotalKiloBytes.".$nm[nmdid].":dcsatnetwork@204.9.216.250:::::2 * 1000\n";
    echo "MaxBytes[".$nm[nmid]."]: 100000\n";
    echo "RouterName[".$nm[nmid]."]: nmname.".$nm[nmdid]."\n";
    echo "Title[".$nm[nmid]."]: ".$nm[nmname]." Traffic\n";
    echo "YLegend[".$nm[nmid]."]: Bits per Second\n";
    echo "LegendI[".$nm[nmid]."]: Downstream:\n";
    echo "LegendO[".$nm[nmid]."]: Upstream:\n";
    echo "PageTop[".$nm[nmid]."]: <h2>".$nm[nmname]."</h2>\n";
    echo "Options[".$nm[nmid]."]: growright,bits,nobanner,nopercent,nolegend\n\n";
  };
};
?>
