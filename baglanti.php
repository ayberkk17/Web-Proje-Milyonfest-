<?php
$host="localhost";
$kullanici="root";
$sifre="";
$vt="uyelik";

$baglanti=mysqli_connect($host,$kullanici,$sifre,$vt);
mysqli_set_charset($baglanti,"UTF8");

?>