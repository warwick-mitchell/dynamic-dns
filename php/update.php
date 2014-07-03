<html>
<body>
<?php
// Config file location
$config_file="/etc/dyndns/dyndns.conf";

// Check we've been passed the user
if (!isset($_SERVER['PHP_AUTH_USER'])) {
	echo "<p>No user received. Check web server configuration</p>";
	exit;
}
$user=$_SERVER['PHP_AUTH_USER'];

// Load config 
$conf = parse_ini_file($config_file, true);

// Check if user exists in config file
if (!isset($conf["users"][$user])) {
	echo "<p>User not found in config file</p>";
	exit;
}

// Read in status file
$status_file = $conf["config"]["status_file"];
$sf = fopen($status_file, 'r');
if (!$sf) {
	echo "<p>Unable to open status file $status_file. Note that if this is a new setup you will need to create an empty file with the correct permisions.</p>";
	exit;
}
$status = array();
while (!feof($sf)) {
	$line = fgets($sf, 4096);
	if ($line) {
		$line = trim($line);
		list($stat_user, $ip) = explode("|", $line, 2);
		$status[$stat_user] = $ip;
	}
}
fclose($sf);

// Check if IP has been updated
$curr_ip=$_SERVER['REMOTE_ADDR'];
if ($curr_ip == $status[$user]) {
	echo "<p>IP $curr_ip unchanged for user $user. Not updating</p>";
	exit;
}

// Update DNS
$dnsname = $conf["users"][$user];
$ns = $conf["config"]["nameserver"];
$bindkey = $conf["config"]["bind_key"];
$ph = popen("/usr/bin/nsupdate -k $bindkey $data", 'w');
if ($ph) {
	fwrite($ph, "server $ns\n");
	fwrite($ph, "update delete $dnsname. A\n");
	fwrite($ph, "update add $dnsname. 60 A $curr_ip\n");
	fwrite($ph, "send\n");
	$ret = pclose($ph);
} else {
	$ret = 255;
}
if ($ret != 0) {
	echo "<p>nsupdate failed with return code $ret. Failed to update $dnsname.</p>";
	exit;
}

// Update status file
$status[$user] = $curr_ip;
$sf = fopen($status_file, 'w');
if (!$sf) {
	echo "<p>Unable to open status file $status_file for writing. Note that if this is a new setup you will need to create an empty file with the correct permisions.</p>";
} else {
	foreach ($status as $stat_user => $ip) {
		fwrite($sf, $stat_user . "|" . $ip . "\n");
	}
	fclose($sf);
}

echo "<p>IP updated for user $user. $dnsname = $curr_ip</p>";

?>
</body>
</html>
