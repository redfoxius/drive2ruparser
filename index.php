<?php
header("Content-type: text/html; charset=utf-8");

require_once 'SHD/simple_html_dom.php';

$baseurl = 'https://www.drive2.ru';

$html = file_get_html($baseurl . '/cars/?all');

$result = array();

foreach ($html->find('span.c-makes__item') as $car) { 
    $carManufacturerName = $car->plaintext;
    $carManufacturerLink = $car->children[0]->attr["href"];
    $data['price'] = $pr.substr($text, 0, 1);
    $result[] = array('Mark' => $carManufacturerName, 'MarkLink' => $baseurl . $carManufacturerLink);
}
$html->clear(); 
unset($html);

var_dump($result);
?>