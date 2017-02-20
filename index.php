<?php
header("Content-type: text/html; charset=utf-8");

require_once 'SHD/simple_html_dom.php';
//require_once 'db.php';

$baseurl = 'https://www.drive2.ru';

$html = file_get_html($baseurl . '/cars/?all');

$result = array();

foreach ($html->find('span.c-makes__item') as $car) { 
    $carManufacturerName = trim($car->plaintext);
    $carManufacturerLink = trim($car->children[0]->attr["href"]);
    exec('php parser.php -n "'.$carManufacturerName.'" -l "'.$baseurl.$carManufacturerLink.'" &');
}
$html->clear(); 
unset($html);

var_dump('ok');
?>