<?php

$kerkkalender_db = dirname(__FILE__) . '/../data/calendar.sqlite3';
$fname_script = __FILE__;
$dirname_cache = dirname(__FILE__) . '/../temp';

function kalender_data($y, $filter, $bisdom)
{
    global $kerkkalender_db;
    
	$data=array();
    $con = new SQLite3($kerkkalender_db, SQLITE3_OPEN_READONLY);
    $con->busyTimeout(5000);
    $statement = $con->prepare(
        'SELECT
            maand,dag,prioriteit,soort_feest,naam_lang,naam_kort,liturgische_tijd,liturgische_kleur,code_eigen,code_gem,code_dag,naam_code
        FROM calendar
        WHERE jaar_vanaf <= :jaar AND jaar_tot >= :jaar AND bereik & :filter AND code_bisdom & :bisdom;'
    );
    $statement->bindValue(':jaar', $y);
    $statement->bindValue(':filter', $filter);
    $statement->bindValue(':bisdom', $bisdom);
//    print $statement->getSQL(true);
    $res = $statement->execute();
    while ($row = $res->fetchArray(SQLITE3_NUM)) {
        $row []= "950";
        $data []= $row;
    }
    $con->close();
 	return $data;
}

function idiv($a,$b)
{
	return floor($a/$b);
}

function paasdatum($y) // berekent de paasdatum van een bepaald jaar
{
	$firstdig1=array(21,24,25,27,28,29,30,31,32,34,35,38);
	$firstdig2=array(33,36,37,39,40);

	$firstdig=idiv($y,100);
	$remain19=$y%19;

	$temp=idiv($firstdig-15,2)+202-11*$remain19;

	if(in_array($firstdig,$firstdig1))
	{
		$temp=$temp-1;
	}
	if(in_array($firstdig,$firstdig2))
	{
		$temp=$temp-2;
	}

	$temp=$temp%30;

	$ta=$temp+21;
	if($temp==29)
	{
		$ta=$ta-1;
	}
	if($temp==28 and $remain19>10)
	{
		$ta=$ta-1;
	}

	$tb=($ta-19)%7;

	$tc=(40-$firstdig)%4;
	if($tc==3)
	{
		$tc=$tc+1;
	}
	if($tc>1)
	{
		$tc=$tc+1;
	}

	$temp=$y%100;
	$td=($temp+idiv($temp,4))%7;

	$te=((20-$tb-$tc-$td)%7)+1;
	$d=$ta+$te;

	if($d>31)
	{
		$d=$d-31;
		$m=4;
	}
	else
	{
		$m=3;
	}

	return array($d,$m);
}

function dmy2n($d,$m,$y)
{
    $date = new DateTime(sprintf('%d-%d-%d', $y,$m,$d));
    list($z) = sscanf($date->format('z'), '%d');
    return $z;
}

function ny2dm($n,$y)
{
    $date = new DateTime(sprintf('%d-01-01', $y));
    $date->add(new DateInterval(sprintf('P%dD', $n)));
    list($d, $m) = sscanf($date->format('j n'), '%d %d');
    return array($d, $m);
}

function kerkkalender($van = NULL, $tot = NULL, $filter = 2, $bisdom = 65535)
/* 1: Eucharistie (oude versie zonder Belgie), 2: Algemeen, 4: Bisdomsite, 8: Eucharistie nieuw */
{
	if (!$van) {
		$van = time();
		$tot = $van;
	}
	if ($tot < $van) {
		$tot = $van;
	}

	$dates = [];
	for ($d = $van; $d <= $tot; $d = strtotime("+1 day", $d))
	{
		$dates []= date('Y-m-d',$d);
	}
	return kerkkalender_list($dates, $filter, $bisdom);
}

function kerkkalender_list($dates, $filter = 2, $bisdom = 65535) {
	$jaren = [];
	$kalender = [];
	foreach ($dates as $date) {
		if (!isset($jaren[$date])) {
			$jaren += json_decode(jaarkalender(intval($date) /* enkel eerste getal == jaar */, $filter, $bisdom), true);
		}
		$kalender[$date] = $jaren[$date];
	}
	return json_encode($kalender);
}

function jaarkalender($y, $filter, $bisdom)
{
    global $kerkkalender_db, $fname_script, $dirname_cache;
    
	$jaarabc=array(0=>"c2",1=>"a1",2=>"b2",3=>"c1",4=>"a2",5=>"b1");
	$data=array();

    $jaarbestand = $dirname_cache . "/jaar" . strval($y) . "_bereik" . $filter . "_bisdom" . $bisdom . ".json";
    if(file_exists($jaarbestand))
    {
        $t=filemtime($jaarbestand);
        if($t>filemtime($fname_script) && $t>filemtime($kerkkalender_db)) // cachebestand bestaat en is niet verouderd
        {
            return file_get_contents($jaarbestand);
        }
    }

    $data=kalender_data($y, $filter, $bisdom);
	$aantal_dagen=dmy2n(31,12,$y)+1;
	$kalender=array();
	for($d=0;$d<2*$aantal_dagen;$d++)
	{
		$kalender[]=array();
	}

	$dm=paasdatum($y);
	$d=$dm[0];
	$m=$dm[1];
	$pasen=dmy2n($d,$m,$y);
	$zondag=$pasen%7;
	$aswoensdag=$pasen-46;
	$goedevrijdag=$pasen-2;
	$pinksteren=$pasen+49;
	$kerstmis=dmy2n(25,12,$y);
	$adventzondag1=$kerstmis-3*7-1;
	while($adventzondag1%7!=$zondag)
	{
		$adventzondag1--;
	}
	$adventzondag1oud=$zondag-35; /* 37 */
	$doorhetjaar=6;
	while($doorhetjaar%7!=$zondag)
	{
		$doorhetjaar++;
	}
	$doopvandeheer=$doorhetjaar;
	if($doopvandeheer<8)
	{
		$doopvandeheer++;
	}
	$pinksteren=$pasen+49;
	$dhjweek=35-idiv($adventzondag1-$pinksteren,7);

	foreach($data as $row)
	{
		if(trim($row[10] ?? '')=="015" && ($kerstmis-$zondag)%7==1) /* 3e advents zondag op 17 december */
		{
			$row[10]="997";
		}
		if(trim($row[8] ?? '')=="990") /* 4e advents zondag */
		{
			$row[8]="".(990+($kerstmis-$zondag)%7);
		}
		switch(trim($row[0] ?? ''))
		{
			case "A":
				$n=$adventzondag1+intval(trim($row[1] ?? ''));
				break;
			case "N": // na adventzondag1 in nieuwe jaar
				$n=$adventzondag1oud+intval(trim($row[1] ?? ''));
				break;
			case "D":
				$dag=intval(trim($row[1] ?? ''));
				if(idiv($dag,7)+1<$dhjweek)
				{
					$n=$doorhetjaar+$dag;
					if($n>$aswoensdag)
					{
						$n=$aswoensdag; // daardoor uitgeschakeld
					}
				}
				else
				{
					$n=$pinksteren+$dag-($dhjweek-1)*7;
				}
				break;
			case "P":
				$n=$pasen+intval(trim($row[1] ?? ''));
				break;
			case "X":
				switch(intval(trim($row[1] ?? '')))
				{
					case 1: /* Heilige familie */
						$n=$kerstmis;
						while($n%7!=$zondag)
						{
							$n++;
						}
						if($n==$kerstmis)
						{
							$n=$kerstmis+5;
						}
						break;
					case 2: /* Openbaring des Heren */
					case 3:
					case 4:
					case 5:
					case 6:
					case 7:
					case 8:
						$n=1;
						while($n%7!=$zondag)
						{
							$n++;
						}
						$n+=intval(trim($row[1] ?? ''))-2;
						break;
					case 9: /* Doop van de Heer */
						$n=$doopvandeheer;
						break;
					case 10: /* Jozef */
						$n=dmy2n(19,3,$y);
						if($n>=$pasen-7 && $n<=$pasen+7) // veranderd op 6-12-13 door Teun: if($n>$pasen-7 && $n<=$pasen+7)
						{
							$n=$pasen-8;
						}
						break;
					case 11: /* Maria boodschap */
						$n=dmy2n(25,3,$y);
						if($n>=$pasen-7 && $n<=$pasen+7)
						{
							$n=$pasen+8;
						}
						break;
					case 12: /* Mirakel van Amsterdam */
						$woensdag=($zondag+3)%7;
						$n=dmy2n(12,3,$y);
						while($n%7!=$woensdag)
						{
							$n++;
						}
						break;
					case 13: /* Bloed van onze Heer Jezus Christus */
						$maandag=($zondag+1)%7;
						$n=dmy2n(3,5,$y);
						while($n%7!=$maandag)
						{
							$n++;
						}
						break;
					case 14: /* Onze Lieve Vrouw ter Nood */
						$zaterdag=($zondag+6)%7;
						$n=dmy2n(31,5,$y);
						while($n%7!=$zaterdag)
						{
							$n--;
						}
						break;
				}
				break;
			default:
				$n=dmy2n(intval(trim($row[1] ?? '')),intval(trim($row[0] ?? '')),$y);
				break;
		}
		$n*=2; // ivm met dubbele lijst per dag: voor vinden hoofditem en de subitems
		if(!isset($kalender[$n]))
			continue;
		$p=intval(trim($row[2] ?? ''));
		if($p==120) /* vrije gedachtenis */
			$p=140; /* allerlaagste prioriteit, dus komt niet aan bod */
		if(strpos($row[4],":")==false) /* algemeen */
		{
			$r=array();
			for($d=0;$d<count($kalender[$n]);$d++) /* plaats huidige geordend naar prioriteit op kalender */
			{
				if($p<$kalender[$n][$d][0]) /* juiste plaats gevonden */
				{
					$r[]=array($p,trim($row[3] ?? ''),trim($row[4] ?? ''),trim($row[5] ?? ''),trim($row[6] ?? ''),trim($row[7] ?? ''),trim($row[8] ?? ''),trim($row[9] ?? ''),trim($row[10] ?? ''),trim($row[11] ?? ''),trim($row[12] ?? ''));
					$p=999; /* voorkomen dat huidige meer keer wordt geplaatst */
				}
				$r[]=$kalender[$n][$d];
			}
			if(count($kalender[$n])==count($r)) /* als huidige niet toegevoegd ivm laagste prioriteit, alsnog als laatste toevoegen */
			{
				$r[]=array($p,trim($row[3] ?? ''),trim($row[4] ?? ''),trim($row[5] ?? ''),trim($row[6] ?? ''),trim($row[7] ?? ''),trim($row[8] ?? ''),trim($row[9] ?? ''),trim($row[10] ?? ''),trim($row[11] ?? ''),trim($row[12] ?? ''));
			}
			$kalender[$n]=$r; /* kalenderdag vernieuwd met toegevoegd item */
		}
		if($p==100 || $p==110 || $p==140 || strpos($row[4],":")!==false) /* (vrije) gedachtenis of lokaal */
		{
			if($p>=100)
			{
				$p=85;
			}

			$r=array(); // toevoegen gerangschikt op prioriteit aan het tweede lijstje
			for($d=0;$d<count($kalender[$n+1]);$d++)
			{
				if($p<$kalender[$n+1][$d][0])
				{
					$r[]=array($p,trim($row[3] ?? ''),trim($row[4] ?? ''),trim($row[5] ?? ''),trim($row[6] ?? ''),trim($row[7] ?? ''),trim($row[8] ?? ''),trim($row[9] ?? ''),trim($row[10] ?? ''),trim($row[11] ?? ''),trim($row[12] ?? ''));
					$p=999;
				}
				$r[]=$kalender[$n+1][$d];
			}
			if(count($kalender[$n+1])==count($r)) // nog niet toegevoegd, daarom alsnog als laatste
			{
				$r[]=array($p,trim($row[3] ?? ''),trim($row[4] ?? ''),trim($row[5] ?? ''),trim($row[6] ?? ''),trim($row[7] ?? ''),trim($row[8] ?? ''),trim($row[9] ?? ''),trim($row[10] ?? ''),trim($row[11] ?? ''),trim($row[12] ?? ''));
			}
			$kalender[$n+1]=$r;
		}
	}
	
	/* 2 verplichte gedachtenissen => 2e naar 2e lijstje met de extra items (bv Onbevlekt hart Maria) */
	
	for($n=0;$n<$aantal_dagen;$n++)
	{
		if(count($kalender[2*$n])>1)
		{
			if($kalender[2*$n][0][0]==100 && $kalender[2*$n][1][0]==100)
			{
				$kalender[2*$n+1][]=$kalender[2*$n][1];
			}
		}
	}		

	/* hoogfeesten verplaatsen */

	for($n=0;$n<$aantal_dagen;$n++)
	{
		if($n>0 && $n%7==$zondag && count($kalender[2*$n])>1)
		{
			if($kalender[2*$n][0][0]==20 && ($kalender[2*$n][1][0]==30 || $kalender[2*$n][1][0]==40))
			{
				$kalender[2*$n+2][0]=$kalender[2*$n][1]; // veranderd op 6-12-2013 door Teun: $kalender[2*$n-2][0]=$kalender[2*$n][1];
				$kalender[2*$n][1][0]=150; // toegevoegd
			}
		}
	}

	for($n=0;$n<$aantal_dagen;$n++)
	{
		if(count($kalender[2*$n])>1)
		{
			if($kalender[2*$n][1][0]==30 || $kalender[2*$n][1][0]==40)
			{
				for($o=$n+1;$o<$aantal_dagen;$o++)
				{
					if($kalender[2*$o][0][0]>80) // veranderd op 6-12-2013 door Teun: if($kalender[2*$n][0][0]>80)
					{
						$kalender[2*$o][0]=$kalender[2*$n][1];
						break;
					}
				}
			}
		}
	}
	for($n=0;$n<$aantal_dagen;$n++)
	{
		$dag=($n-$zondag+7)%7;
		$afgelopenzondag=$n-$dag;
		$dm=ny2dm($n,$y);
		$jaar=$y;
		if($n>=$adventzondag1)
			$jaar++;
		if($afgelopenzondag<$doorhetjaar)
		{
			$week="Kersttijd";
		}
		elseif($afgelopenzondag<$aswoensdag)
		{
			$week=strval(idiv($afgelopenzondag-$doorhetjaar,7)+1)."e week door het jaar";
			if(($afgelopenzondag+7)>$aswoensdag)
			{
				$week.=" / Veertigdagentijd";
			}
		}
		elseif($afgelopenzondag<$pasen-7)
		{
			$week=strval(-idiv(($pasen-7)-$afgelopenzondag,7)+6)."e week in de veertigdagentijd";
		}
		elseif($afgelopenzondag==$pasen-7)
		{
			$week="Goede week";
		}
		elseif($afgelopenzondag==$pasen)
		{
			$week="Octaaf van Pasen";
		}
		elseif($afgelopenzondag<$pinksteren)
		{
			$week=strval(idiv($n-$pasen,7)+1)."e week na Pasen";
		}
		elseif($afgelopenzondag<$adventzondag1)
		{
			$week=strval(idiv($afgelopenzondag-$pinksteren,7)+$dhjweek)."e week door het jaar";
		}
		elseif($afgelopenzondag<=$adventzondag1+3*7)
		{
			$adventweek=idiv($afgelopenzondag-$adventzondag1,7)+1;
			$week=strval($adventweek)."e week van de advent";
			if($adventweek==4 && ($kerstmis-$zondag)%7!=0)
			{
				$week.=" / Kersttijd";
			}
		}
		else
		{
			$week="Kersttijd";
		}
		$vandedag=""; // vinden van dagcode en liturgische tijd
		for($x=0;$x<count($kalender[2*$n]);$x++)
		{
			if($kalender[2*$n][$x][4])
		   {
			$detijd=$kalender[2*$n][$x][4];
			break;
			}
		}
		for($x=0;$x<count($kalender[2*$n]);$x++)
		{
			if($kalender[2*$n][$x][8])
		   {
			$vandedag=$kalender[2*$n][$x][8];
			break;
			}
		}
		$kalender[2*$n][0][4]=$detijd; // wegschrijven in alle kalender items
		$kalender[2*$n][0][8]=$vandedag;
		for($x=0;$x<count($kalender[2*$n+1]);$x++)
		{
			$kalender[2*$n+1][$x][4]=$detijd;
			$kalender[2*$n+1][$x][8]=$vandedag;
		}
		$k=array($kalender[2*$n][0]); // toevoegen hoofditem van de dag
		for($x=0;$x<count($kalender[2*$n+1]);$x++) // toevoegen subitems
		{
			if($kalender[2*$n+1][$x][0]>$kalender[2*$n][0][0] // subitem van lagere prioriteit
				|| ($kalender[2*$n+1][$x][0]==85 && $kalender[2*$n][0][0]==100)) // lokale/vrije gedachtenis terwijl er al een verplichte gedachtenis is
				continue; // wordt niet toegevoegd
			$kalender[2*$n+1][$x][]=$vandedag; // waarom deze regel??? lijkt een overbodig extra item toe te voegen...
			if($kalender[2*$n][0][0]==90 && $kalender[2*$n+1][$x][0]==85) // optionele co-gedachtenis
			{
				$kalender[2*$n+1][$x][5]=$kalender[2*$n][0][5]; // kleur van dag
				$kalender[2*$n+1][$x][1]=""; // "g" van verplicht verwijderen
			}
			$k[]=$kalender[2*$n+1][$x]; // anders wel
		}
		$strdate = sprintf('%04d-%02d-%02d', $y, $dm[1], $dm[0]);
		$today = strtotime($strdate);
		$tomorrow = strtotime('+1 day', $today);
		$jaarkalender[$strdate] = array();
        if ($k[0][4] == 'a' || $today > strtotime(sprintf('%04d-12-03', $y)))
        {
            $yearABC = ($y % 3)? ((($y % 3) == 1)? 'b': 'c'): 'a';
            $year12 = ($y % 2)? 2: 1;
        }
        else
        {
            $yearABC = ($y % 3)? ((($y % 3) == 1)? 'a': 'b'): 'c';
            $year12 = ($y % 2)? 1: 2;
        }
        $jaarkalender[$strdate]['weekTitle'] = $week;
		$jaarkalender[$strdate]['weekISO'] = intval(date('W', $today));
		$jaarkalender[$strdate]['weekISOCorrected'] = intval(date('W', $tomorrow));
		$jaarkalender[$strdate]['weekDay'] = intval(date('N', $today));
		$jaarkalender[$strdate]['weekDayCorrected'] = intval(date('N', $tomorrow));
		$jaarkalender[$strdate]['yearABC'] = $yearABC;
		$jaarkalender[$strdate]['year12'] = $year12;
		$jaarkalender[$strdate]['season'] = $k[0][4];
		$jaarkalender[$strdate]['items'] = [];
	    
        
		foreach ($k as $kk)
		{
			$jaarkalender[$strdate]['items'][] = array
			(
				'priority' => $kk[0],
				'type' => $kk[1],
				'titleLong' => $kk[2],
				'titleShort' => $kk[3],
                'titleCode' => $kk[9],
				'color' => $kk[5],
				'codeProper' => $kk[6],
				'codeCommon' => $kk[7],
				'codeDay' => $kk[8],
				'codeExtra' => $kk[10],
			);
		}
	}
    
	$json_kalender = json_encode($jaarkalender);
    $erlevel = error_reporting(0);
	file_put_contents($jaarbestand,$json_kalender); // sla op in cache bestand
	error_reporting($erlevel);
	return $json_kalender;
}