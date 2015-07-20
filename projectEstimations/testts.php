<?php


$no1 = 18000;
$no2 = 2384;


//echo round( (($no1/3600)/6),1, PHP_ROUND_HALF_UP);

$percent = $no2/$no1;
$percent_friendly = number_format(100 - ($percent * 100), 0 ) . '%';

echo $percent_friendly;