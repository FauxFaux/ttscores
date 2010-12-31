<html><head>
<style type="text/css">
	td,th { border: 1px solid black; padding: 0.5em }
	.right { text-align: right }
</style>
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
	$esc = htmlentities($name);
	return "<a href=\"?track=$n#$esc\">$n. $esc</a>";
}

function player($name) {
	$esc = htmlentities($name);
	return '<a href="?player=' . urlencode(htmlentities($esc)) . "\">$esc</a>";
}

$date = stat('tt.db');
$date = $date[9];
$dbh = new PDO('sqlite:tt.db');

function players() {
	global $dbh;
	$prevtrack = -1;
	$players = array();
	foreach ($dbh->query('select track,player,length,hard from highscore where pos<=50 order by track,length') as $row) {
		if (!$row['player'])
			continue;
		$n = $row['track'];
		$len = $row['length'];
		if ($prevtrack != $n) {
			$prevtrack = $n;
			$prevlen = $len;
			$points = 10;
		}

		if ($points <= 0)
			continue;

		if ($prevlen != $len) {
			--$points;
			$prevlen = $len;
		}

		$players[$row['player']] += $points + $row['hard'];
	}
	return $players;
}

if (null !== $track) {
	foreach ($dbh->query('select name from track_names where track=' . $track) as $row)
		echo htmlentities($row["name"]) . "</title></head><body><h2>" . track($track, $row['name']) . '</h2>';
	echo '<table><tr><th>pos</th><th>name</th><th>time</th></tr>';
	$pos = 0;
	$prevtime = 0;
	foreach ($dbh->query('select player pwner, length ' .
				'from highscore ' .
				'inner join track_names using (track) ' .
				'where track=' . $track) as $row) {
		$len = $row['length'];
		if ($len != $prevtime) {
			$prevtime = $len;
			++$pos;
		}
		echo "<tr><td>$pos</td><td>" . player($row['pwner']) . "</td><td>" . number_format($len,2) . "</td></tr>\n";
	}
	echo "</table>";
} else if (null !== $player) {
	$esc = htmlentities($player);
	$quoted = $dbh->quote($player);
	$players = players();
	echo "player: $esc</title></head><body><h2>$esc: " . $players[$player] . " points</h2>";

	echo "<table><tr><th>track</th><th>pos</th><th>first</th><th>player</th><th>pace</th></tr>\n";
	$q = 'select track n,name,pos,first,you,(you/first-1)*100 pace from (' .
	'select a.track,' .
	'(select length from highscore b where track=a.track and pos=1) first,' .
	'(select length from highscore b where track=a.track and player=' . $quoted . ') you, ' .
	'(select pos from highscore b where track=a.track and player=' . $quoted . ') pos ' .
	'from highscore a ' .
	'group by a.track ' .
	') join track_names using (track) ' .
	'where first is not null and you is not null ' .
	'order by pace asc,n';
	foreach ($dbh->query($q) as $row)
		echo "<tr><td>" . track($row['n'], $row['name']) . "</td><td class=\"right\">{$row['pos']}</td><td class=\"right\">" . number_format($row['first'], 2) . "</td><td class=\"right\">" . number_format($row['you'], 2) . "</td><td class=\"right\">" . number_format($row['pace']) . "%</td></tr>";
	echo "</table>";
} else {
	echo "summary</title></head><body>";
	$players = players();
	$scores = array();
	foreach ($players as $player => $score)
		$scores[$score][] = $player;
	krsort($scores);

	$pos = 0;
	echo "<h2>championship</h2><table><tr><th>pos</th><th>points</th><th>name</th></tr>";
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
	echo '<p><form action="/" method="GET">View player: <input type="text" name="player"/><input type="submit" value="lookup"/></form></p>';
	echo "<h2>tracks</h2><table><tr><th>track</th><th>hs</th><th>length</th><th>winners</th></tr>";
	$prevn = -1;
	foreach ($dbh->query('select track n, name, length, player pwner, pos, (select count(*) from highscore b where a.track=b.track and player !="") cnt ' .
				'from highscore a ' .
				'inner join track_names using (track) ' .
				'where pos <= 3 and pwner != "" order by track,pos') as $row) {
		if ($prevn != $row['n']) {
			if ($prevn != -1)
				echo "</td></tr>\n";
			echo "<tr><td>" . track($row['n'], $row['name']) . "</td><td class=\"right\">{$row['cnt']}</td><td class=\"right\">" . number_format($row['length'], 2) . "</td><td>";
			$prevn = $row['n'];
			$s = "";
		}

		echo suffix($row['pos']) . ': ' . player($row['pwner']) . ", ";
	}
	echo "</table>";
} 
?>
<p>Data from <?=date(DATE_RFC822, $date)?></p>
<p><a href="/">back</a></p>
<p><a href="http://blog.prelode.com/">Faux' blog</a></p>
</body>
</html>

