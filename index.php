<<?='?'?>xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC
  "-//W3C//DTD XHTML Basic 1.1//EN"
    "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">
<html><head>
<style type="text/css">
	td,th { border: 1px solid black; padding: 0.5em }
	.right { text-align: right }
	.sortable tr th { cursor: pointer }
</style>
<script src="sorttable.js" type="text/javascript"></script>
<title>ttscores: <?

if (isset($_GET['track']))
	$track = (int)$_GET['track'];

if (isset($_GET['player']))
	$player = $_GET['player'];

function suffix($number) {
	$n = $number % 100;
	if ( $n > 3 && $n < 21 )
		return $number . 'th';
	switch ( $n % 10 ) {
		case '1': return $number . 'st';
		case '2': return $number . 'nd';
		case '3': return $number . 'rd';
		default:  return $number . 'th';
	}	
}

function track($n, $name) {
	global $inctrackurl;
	$esc = htmlentities($name);
	return "<a href=\"?track=$n$inctrackurl#$esc\">$esc</a>";
}

function completed() {
	global $dbh;
	$ret = array();
	foreach ($dbh->query('select player,count(*) cnt from highscore where 1 ' . inctrack() . ' group by player') as $row)
		$ret[$row['player']] = $row['cnt'];
	return $ret;
}

function player($name) {
	global $inctrackurl, $completed;
	$esc = htmlentities($name);
	return '<a href="?player=' . urlencode(htmlentities($esc)) . "$inctrackurl\">$esc ({$completed[$name]})</a>";
}

function inctrack() {
	global $inctrack;
	if ($inctrack)
		return "and track in ($inctrack)";
}

$inctrack = array();
if (isset($_GET{'tracks'}))
	foreach (preg_split('/,/', $_GET{'tracks'}) as $t)
		$inctrack[] = (int)$t;
sort($inctrack);
$inctrack = implode(',',$inctrack);
if ($inctrack)
	$inctrackurl = "&tracks=" . $inctrack;

$date = stat('tt.db');
$date = $date[9];
$dbh = new PDO('sqlite:tt.db');

$completed = completed();

$back = "(<a href=\"/?$inctrackurl\">back to championship</a>) ";

function completers() {
	global $dbh;
	$ret = array();
	foreach ($dbh->query('select track,count(*) cnt from highscore where player!="" group by track') as $row)
		$ret[$row['track']] = $row['cnt'];
	return $ret;
}

function points($points, $total) {
	return 10 * pow(0.05,($points)/$total);
}

function players() {
	global $dbh;
	$prevtrack = -1;
	$completers = completers();
	$players = array();
	foreach ($dbh->query('select track,player,length,hard from highscore ' .
			'where pos<=50 and player!="" ' . inctrack() . ' order by track,length') as $row) {
		if (!$row['player'])
			continue;
		$n = $row['track'];
		$len = $row['length'];
		if ($prevtrack != $n) {
			$prevtrack = $n;
			$prevlen = $len;
			$total = $completers[$n];
			$points = 0;
		}

		if ($prevlen != $len) {
			$points += $skip;
			$skip = 1;
			$prevlen = $len;
		} else
			++$skip;

		$players[$row['player']] += points($points, $total);
	}
	return $players;
}

$sortdown = '<span id="sorttable_sortfwdind">&nbsp;&#x25BE;</span>';
$sortup = '<span id="sorttable_sortrevind">&nbsp;&#x25B4;</span>';

if (null !== $track) {
	foreach ($dbh->query('select name from track_names where track=' . $track) as $row)
		echo htmlentities($row["name"]) . "</title></head><body><h2>$track. " . track($track, $row['name']) . '</h2>';
	echo "<p>$back</p>" . '<table class="sortable"><tr><th>pos</th><th>name</th><th>time</th></tr>';
	$pos = 1;
	$prevtime = 0;
	foreach ($dbh->query('select player pwner, length ' .
				'from highscore ' .
				'inner join track_names using (track) ' .
				'where pwner != "" and track=' . $track) as $row) {
		$len = $row['length'];
		if ($len != $prevtime) {
			$prevtime = $len;
			$pos+=$skip;
			$skip = 1;
		} else
			++$skip;
		echo "<tr><td>$pos</td><td>" . player($row['pwner']) . "</td><td>" . number_format($len,2) . "</td></tr>\n";
	}
	echo "</table><p>$back</p>";
} else if (null !== $player) {
	$esc = htmlentities($player);
	$quoted = $dbh->quote($player);
	$players = players();
	$completers = completers();
	$linktracks = '(<a href="#track">top</a>) ';
	$linkgame = '(<a href="#game">tracks to game</a>) ';
	$linkrisk = '(<a href="#risk">scores at risk</a>) ';

	echo "player: $esc</title></head><body><h2>" . player($player) . ": " . number_format($players[$player],1) . " points</h2>";

	echo "<p>$back<a name=\"track\"/>$linkgame$linkrisk</p><table class=\"sortable\"><tr><th>n</th><th>track</th><th>p</th><th>of</th><th>points</th>" .
		"<th>first</th><th>player</th><th class=\"sorttable_sorted\">pace$sortdown</th></tr>\n";
	$q = 'select track n,name,pos,first,you,(you/first-1)*100 pace from (' .
		'select a.track,' .
		'(select length from highscore b where track=a.track and pos=1) first,' .
		'(select length from highscore b where track=a.track and player=' . $quoted . ') you, ' .
		'(select pos from highscore b where track=a.track and player=' . $quoted . ') pos ' .
		'from highscore a ' .
		'group by a.track ' .
		') join track_names using (track) ' .
		'where first is not null and you is not null ' . inctrack() .
		'order by pace asc,n';
	$ns = array();
	foreach ($dbh->query($q) as $row) {
		$ns[] = $row['n'];
		echo "<tr><td class=\"right\">{$row['n']}</td><td>" . track($row['n'], $row['name']) . "</td>" .
			"<td class=\"right\">{$row['pos']}</td><td class=\"right\">{$completers[$row['n']]}</td>" .
			"<td class=\"right\" sorttable_customkey=\"" . points($row['pos'], $completers[$row['n']]) . "\">" . 
				number_format(points($row['pos'], $completers[$row['n']]), 1) . "</td>" .
			"<td class=\"right\">" . number_format($row['first'], 2) . "</td>" .
			"<td class=\"right\">" . number_format($row['you'], 2) . "</td>" .
			"<td class=\"right\">" . number_format($row['pace']) . "%</td></tr>";
	}
	echo "</table><p>";
	sort($ns);
	$newinc = implode(',', $ns);
	if ($inctrack != $newinc)
		echo "(<a href=\"/?tracks=$newinc\">" . ($inctrack ? "union" : "set") . " track filter</a>)";

	if ($inctrack)
		echo " (<a href=\"?player=" . urlencode($esc) . "\">clear track filter</a>)";
	echo "</p><h2>tracks to game</h2><p>(...to increase your championship score.  You know you want to.)</p><p>$back<a name=\"game\"/>$linktracks$linkrisk</p>" .
		"<table class=\"sortable\"><tr><th>n</th><th>name</th><th>len</th><th>position$sortup</th><th>est. points</th></tr>";

	foreach ($dbh->query('select track n,name, '.
			'coalesce((select pos from highscore b where player=' . $quoted . ' and a.track=b.track),count(*)) cnt, '.
			'(select length from highscore b where pos=1 and a.track=b.track) length '.
			'from highscore a inner join track_names using (track) where 1 ' . inctrack() . ' ' .
			'group by track order by cnt desc limit 30') as $row) {
		$compl = $completers[$row['n']];
		echo "<tr><td class=\"right\">{$row['n']}</td><td>" . track($row['n'], $row['name']) . '</td>' .
			'<td class="right">' . number_format($row['length'],2) . '</td>' .
			'<td class="right">' . $row['cnt'] . '</td>' .
			'<td class="right">' . number_format(points(1, $compl) - points($row['cnt'], $compl), 1) . '</td></tr>';
	}
	echo '</table>';

	echo '<h2>scores at risk</h2><p><a name="risk"/>' . $back . $linktracks . $linkgame . '</p><table class="sortable"><tr><th>n</th><th>name</th><th>hours</th></tr>';
	foreach ($dbh->query('select track n,name,(14*24)-(strftime(\'%s\',\'now\')-taken/1000)/60./60 hours ' .
			'from highscore inner join track_names using (track) ' .
			'where player=' . $quoted . ' and taken!=0 ' . inctrack() . ' order by taken limit 10') as $row)
		echo "<tr><td class=\"right\">{$row['n']}</td><td>" . track($row['n'], $row['name']) . '</td>' .
			 '<td class="right">' . number_format($row['hours']) . '</td></tr>';
	
	echo "</table><p>$back$linktracks$linkgame$linkrisk</p>";
} else {
	echo "summary</title></head><body>";
	$players = players();
	$scores = array();
	foreach ($players as $player => $score) {
		$scores[round($score)][] = $player;
	}
	krsort($scores);

	$pos = 0;
	$alist = '3,4,5,7,8,0,1,423,10,9,6';
	echo '<h2>championship</h2><p>(sum(10*0.05^(pos/completed))) <a name="top"/>(<a href="#tracks">list of tracks</a>)</p><p>';
	if ($inctrack != $alist)
		echo "(<a href=\"?tracks=$alist\">A-list tracks only</a>)";
	if ($inctrack)
		echo " (<a href=\"?\">clear track filter</a>)";
	echo "</p><table><tr><th>pos</th><th>points</th><th>name</th></tr>";
	foreach ($scores as $score => $players) {
		++$pos;
		echo "<tr><td class=\"right\">$pos</td><td class=\"right\">$score</td><td>";
		if (count($players) != 1) {
			echo "<ul>";
			foreach ($players as $player)
				echo "<li>" . player($player) . "</li>\n";
			echo "</ul>";
		} else
			echo player($players[0]);
		echo "</td></tr>\n";
		if ($pos >= 20)
			break;
	}

	echo "</table>";
	echo '<form action="/" method="get"><p>View player: <input type="text" name="player"/><input type="submit" value="lookup"/></p></form>';
	echo '<h2>tracks</h2><p><a name="tracks"/>(<a href="#top">top</a>)</p><table class="sortable"><tr><th>n</th><th>track</th><th>hs</th><th>length</th><th>winners</th></tr>';
	$prevn = -1;
	foreach ($dbh->query('select track n, name, length, player pwner, pos, ' .
			'(select count(*) from highscore b where a.track=b.track and player !="") cnt ' .
			'from highscore a ' .
			'inner join track_names using (track) ' .
			'where pos <= 3 and pwner != "" ' . inctrack() . ' order by track,pos') as $row) {
		if ($prevn != $row['n']) {
			if ($prevn != -1)
				echo "</td></tr>\n";
			echo "<tr><td>{$row['n']}</td>" .
				"<td sorttable_customkey=\"" . htmlentities($row['name']) . "\">" . 
					track($row['n'], $row['name']) . "</td>" .
				"<td class=\"right\">{$row['cnt']}</td>" .
				"<td class=\"right\">" . number_format($row['length'], 2) . "</td><td>";
			$prevn = $row['n'];
			$s = "";
		}

		echo suffix($row['pos']) . ': ' . player($row['pwner']) . ", ";
	}
	echo '</td></tr></table><p>(<a href="#top">top</a>) (<a href="#tracks">list of tracks</a>)</p>';
} 
?>
<p>Data from <?=date(DATE_RFC822, $date)?></p>
<p>This site is free software; <a href="http://git.goeswhere.com/?p=ttscores.git;a=summary">its source</a> is available.
I encourage you to submit or suggest changes instead of hosting your own.</p>
<p><a href="http://blog.prelode.com/">Faux' blog</a>.
Thanks to archee for <a href="http://www.gravitysensation.com/trickytruck/">Tricky Trucks</a>.</p>
</body>
</html>

