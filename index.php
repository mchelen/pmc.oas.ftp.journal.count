<?php
/**
 * author: Michael Chelen http://mikechelen.com http://twitter.com/mikechelen
 * license: Creative Commons Zero
 * downloads pmc open access subset ftp file list and computes article counts for each journal
 * source: ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt
 */
// file url
$url = "ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt";
// xml file name
$xmlfilename = "output.xml";
// csv file name
$csvfilename = "output.csv";
// open remote file
$handle = fopen($url, "r");
if ($handle) {
    // read first line
    $firstline = fgets($handle);
    fclose($handle);
}
// reformat first line for usage as timestamp
$timestamp=trim($firstline);

$filename = "file_list.".preg_replace('/[^0-9]/',".",$timestamp).".txt";

if (!file_exists($filename)) {
// download file with wget
  $output = `wget -O $filename $url`;
}
// open local file
$handle = fopen($filename, "r");
// skip first line
fgets($handle);
// initialize journal array
$journals = array();
// read file as csv (tsv)
while (($data = fgetcsv($handle, 0, chr(9))) !== FALSE) {
// store journal name
  preg_match('/^[^\.]+/', $data[1],$matches);
  $key = $matches[0];
// check if journal count exists
  if (array_key_exists($key,$journals)) {
//  increment journal count
    $journals[$key]=$journals[$key]+1;
  }
  else {
//  start new journal count
    $journals[$key]=1;
  }
}
// close file
fclose($handle);
// get requested format
$format=$_GET['format'];
// create dom document
$doc = new DOMDocument('1.0', 'UTF-8');
// create root
$root = $doc->createElement("root");
// attach root
$doc->appendChild($root);
// create recordset
$recordset = $doc->createElement("dataset");
// append recordset to root
$root->appendChild($recordset);
// create date attribute
$attr_date = $doc->createAttribute('date');
// append attribute to row
$recordset->appendChild($attr_date);
// create date node
$date = $doc->createTextNode($timestamp);
// append date to attribute
$attr_date->appendChild($date);
foreach ($journals as $key => $value) {
//  echo "$key = $value, ";
    
//  create row
    $row = $doc->createElement("journal",$value);
//  append row to recordset
    $recordset->appendChild($row);
//  create title attribute
    $attr = $doc->createAttribute('title');
//  append attribute to row
    $row->appendChild($attr);
//  create title node
    $title = $doc->createTextNode($key);
//  append title to attribute
    $attr->appendChild($title);
}

// load xml file if it exists
if (file_exists($xmlfilename)) {
//  load xml
  $olddoc = new DOMDocument();
  $olddoc->load($xmlfilename);
//  apply xpath to find date
  $xpath = new DOMXpath($olddoc);
  $elements = $xpath->query("//dataset/@date");
  if (!is_null($elements)) {
    foreach ($elements as $element) {
        $lastdate = $element->nodeValue;
    }
  }
//  check if current timestamp differs from old date
  if ($timestamp!=$lastdate) {
//    echo "Timestamp: $timestamp Lastdate: $lastdate";
//    import new recordset to old xml
    $node = $olddoc->importNode($recordset, true);
//    make it pretty
    $node->formatOutput=true;
    $olddoc->documentElement->appendChild($node);
//    make it pretty
    $olddoc->formatOutput=true;
//    write XML file
    $fh = fopen($xmlfilename, 'w') or die("can't open file");
    fwrite($fh, $olddoc->saveXML());
    fclose($fh);
    print "appended data to xml file <a href=\"$xmlfilename\">$xmlfilename</a>";
  }
  else {
  print "matching timestamp $timestamp xml file untouched <a href=\"$xmlfilename\">$xmlfilename</a>";
  }
}
// xml output file does not exist
else {
//  make it pretty
  $doc->formatOutput=true;
//  write XML file
  $fh = fopen($xmlfilename, 'w') or die("can't open file");
  fwrite($fh, $doc->saveXML());
  fclose($fh);
  print "wrote new xml file <a href=\"$xmlfilename\">$xmlfilename</a>";
}
?>
