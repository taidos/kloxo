<?php 

function get_deny_list($total)
{
    $lxgpath = "__path_home_root/lxguard";
    $rmt = lfile_get_unserialize("$lxgpath/config.info");
    $wht = lfile_get_unserialize("$lxgpath/whitelist.info");
    if ($wht) {$wht = $wht->data;}
    $disablehit = null;
    if ($rmt) {
        $disablehit = $rmt->data['disablehit'];
    }
    if (!($disablehit > 0)) { $disablehit = 20 ;}
    $deny = null;
    if ($total) {
    foreach($total as $k => $v) {
        if ($wht) {
            if (array_search_bool($k, $wht)) {
                dprint("$k found in whitelist... not blocking..\n");
                continue;
            }
        }

        if ($v > $disablehit) {
            $deny[$k] = $v;
        }
    }
    }
    return $deny;
}

function get_total($list, &$total)
{
	$lxgpath = "__path_home_root/lxguard";
	$rmt = lfile_get_unserialize("$lxgpath/hitlist.info");
	if ($rmt) { $total = $rmt->hl; }
    if ($list) {
        foreach($list as $k => $v) {
            if (!isset($total[$k])) { $total[$k] = 0 ; }
            $c = count_fail($v);
            $total[$k] += $c;
        }
    }
}

function count_fail($v)
{
	$count = 0;
	foreach($v as $vv) {
		if ($vv['access'] === 'fail') {
			$count++;
		}
	}
	return $count;
}

function parse_sshd_and_ftpd($fp, &$list)
{
	$count = 0;

	while(!feof($fp)) {
		$count++;

	//	if ($count > 10000) { break; }

		$string = fgets($fp);

		if (strpos($string, 'sshd') !== false) {
			sshLogString($string, $list);
		} elseif (strpos($string, 'pure-ftpd') !== false) {
			ftpLogString($string, $list);
		}
	}
}

function parse_ssh_log($fp, &$list)
{
	$count = 0;
	$string = '';

	while(!feof($fp)) {
		$count++;

	//	if ($count > 10000) { break; }

		$string = fgets($fp);

		if (strpos($string, 'sshd') !== false) {
			sshLogString($string, $list);
		}
	}
}

function parse_ftp_log($fp, &$list)
{
	$count = 0;
	$string = '';

	while(!feof($fp)) {
		$count++;

	//	if ($count > 10000) { break; }

		$string = fgets($fp);

		if (strpos($string, 'pure-ftpd') !== false) {
			ftpLogString($string, $list);
		}
	}
}

function sshLogString($string, &$list)
{
	//'refuse' => "refused connection",
	$str = array('success' => "Accepted password",  'fail' => "Failed password");
	$match = false;

	foreach($str as $k => $v) {
		if (strpos($string, $v) !== false) {
			$match = true;
			$access = $k;

			break;
		}
	}

	if (!$match) { return; }

	$time = getTimeFromSysLogString($string);

	if ($access === 'fail') {
		preg_match("/.*Failed password for( invalid user)? (.*) from ([^ ]*).*/", $string, $match);

		$ip = $match[3];
		$user = $match[2];
	} else {
		preg_match("/.*Accepted password for (.*) from ([^ ]*).*/", $string, $match);

		$ip = $match[2];
		$user = $match[1];
	}

	if (csb($ip, "::ffff:")) {
		$ip = strfrom($ip, "::ffff:");
	}

	if (!$match) { return; }

	if (csb($ip, "::ffff:")) {
		$ip = strfrom($ip, "::ffff:");
	}

	if (csb($ip, "127")) { return; }

	$list[$ip][$time] = array('service' => 'ssh', 'user' => $user, 'access' => $access);
}

function ftpLogString($string, &$list)
{
	$str = array('fail' => "Authentication failed",  'success' => "is now logged in");
	$match = false;

	foreach($str as $k => $v) {
		if (strpos($string, $v) !== false) {
			$match = true;
			$access = $k;

			break;
		}
	}

	if (!$match) { return; }

	$time = getTimeFromSysLogString($string);

	if ($access === 'fail') {
		preg_match("/.*\(?@([^\)]*)\) \[WARNING\] Authentication failed for user \[([^\]]*)\].*/", $string, $match);
	} else {
		preg_match("/.*\(?@([^\)]*)\) \[INFO\] ([^ ]*) is now logged in.*/", $string, $match);
	}

	if (!$match) { return; }

	$ip = $match[1];
	$user = $match[2];

	if (csb($ip, "127")) { return; }

	$list[$ip][$time] = array('service' => 'ftp', 'user' => $user, 'access' => $access);
}

function getTimeFromSysLogString($line)
{
	
	$line = trimSpaces($line);
	$year = @ date('Y');
	list($month, $day, $time) = explode(" ", $line);
	$month = get_num_for_month($month);
	list($hour, $min, $sec) = explode(':' , $time);
	//$s  =  mktime($hour , $min , $sec , monthToInt($month), str_pad($day , 2, 0, STR_PAD_LEFT) , $year);
	$s  =  @ mktime($hour, $min, $sec, $month, $day, $year);
	//dprint(" $date $time $hour, $min $sec $month, $day , $year, Time: $s\n");
	// Return date and size. The size param is not important. Our aim is to find the right position.
	return $s;
}