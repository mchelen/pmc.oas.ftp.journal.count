<?php

// functions

function processfile($sourcefile,$outputfile,$outputpath){
  // get journal counts from source file
  if(webpage()){print "<br />";}
  print "Getting journal counts from $sourcefile\n";
  $journalcounts = getjournalcounts($sourcefile);
  // generate xml
  $xml = makexml($journalcounts["journals"], $journalcounts["timestamp"]);
  $doc = $xml["doc"];
  $recordset = $xml["recordset"];
  // load xml file if it exists
  if (file_exists($outputfile)) {
    // append new xml to existing
    if (webpage()) { print "<br />";}
    print "Appending data to xml file ";
    if (webpage()) { print "<a href=\"$outputfile\">$outputfile</a>\n";}
    else { print "$outputfile\n";}
    appendxml($outputfile, $recordset);
  }
  else {
  // print writing new xml file messages
    if (webpage()) { print "<br />Writing new xml file <a href=\"$outputfile\">$outputfile</a>\n";}
    else { print "Writing new XML file $outputfile \n";}
    // xml output file does not exist
    $output = `mkdir -p $outputpath`;
    writenewxml($doc, $outputfile);
  }
 
}
function webpage() {
  if(empty($_SERVER['REMOTE_ADDR'])){
    return false;
  }
  else {
    return true;
  }
}
function gettimestamp($url) {
  // gets timestamp from first line of remote file
  // open remote file
  $handle = fopen($url, "r");
  if ($handle) {
      // read first line
      $firstline = fgets($handle);
      fclose($handle);
  }
  // trim first line for usage as timestamp
  $timestamp=trim($firstline);
  // return trimmed timestamp
  return $timestamp;
}
function downloadfile($sourcepath, $sourcefile, $url) {
  // file does not exist
  // create source file directory
  $output = `mkdir -p $sourcepath`;
  // download file with wget
  $output = `wget -O $sourcefile $url`;
}
function getjournalcounts($sourcefile) {
  // get journal counts from source file
  // open local file
  $handle = fopen($sourcefile, "r");
  // read timestamp
  if ($handle) {
    $timestamp = trim(fgets($handle));
  }
  // initialize journal array
  $journals = array();
  // read file as csv (tsv)
  while (($data = fgetcsv($handle, 0, chr(9))) !== FALSE) {
    // store journal name
    preg_match('/^[^\.]+/', $data[1],$matches);
    $key = $matches[0];
    // check if journal count exists
    if (array_key_exists($key,$journals)) {
      // increment journal count
      $journals[$key]=$journals[$key]+1;
    }
    else {
      // start new journal count
      $journals[$key]=1;
    }
  }
  // close file
  fclose($handle);
  $result["journals"] = $journals;
  $result["timestamp"] = $timestamp;
  return $result;
}
function makexml($journals, $timestamp) {
  // create dom document
  $doc = new DOMDocument('1.0', 'UTF-8');
  // create root element
  $root = $doc->createElement("root");
  // attach root element to document
  $doc->appendChild($root);
  // create recordset element
  $recordset = $doc->createElement("dataset");
  // append recordset element to root
  $root->appendChild($recordset);
  // create date attribute
  $attr_date = $doc->createAttribute('date');
  // append attribute to recordset element
  $recordset->appendChild($attr_date);
  // create date node
  $date = $doc->createTextNode($timestamp);
  // append date to attribute
  $attr_date->appendChild($date);
  foreach ($journals as $key => $value) {
  //  create row element
      $row = $doc->createElement("journal",$value);
  //  append row to recordset element
      $recordset->appendChild($row);
  //  create title attribute
      $attr = $doc->createAttribute('title');
  //  append attribute to row element
      $row->appendChild($attr);
  //  create title node
      $title = $doc->createTextNode($key);
  //  append title to attribute
      $attr->appendChild($title);
  }
  $result["doc"]=$doc;
  $result["recordset"]=$recordset;
  return $result;
}
function appendxml($outputfile, $recordset) {
    // load xml
    $olddoc = new DOMDocument();
    $olddoc->load($outputfile);
    // import new recordset to old xml
    $node = $olddoc->importNode($recordset, true);
    // make it pretty
    $node->formatOutput=true;
    $olddoc->documentElement->appendChild($node);
    // make it pretty
    $olddoc->formatOutput=true;
    // write XML file
    $fh = fopen($outputfile, 'w') or die("can't open file");
    fwrite($fh, $olddoc->saveXML());
    fclose($fh);
}
function writenewxml($doc, $outputfile) {
    // make it pretty
    $doc->formatOutput=true;
    // write XML file
    $fh = fopen($outputfile, 'w') or die("can't open file");
    fwrite($fh, $doc->saveXML());
    fclose($fh);
}
function makeflotjs($outputfile, $outputpath) {
  $doc = new DOMDocument;
  $doc->Load($outputfile);
  $journalhistories = array();
  $numdatasets=0;
  $xpath = new DOMXPath($doc);
  $query = "//dataset";
  $entries = $xpath->query($query);
  foreach ($entries as $entry) {
    $numdatasets++;
  }
  $xpath = new DOMXPath($doc);
  for ($i=0;$i<=$numdatasets;$i++) {
    $query = "//dataset[$i]/journal";
  //  print "Performing query: $query \n";
    $entries = $xpath->query($query);
    foreach ($entries as $entry) {
      $timestamp = $entry->parentNode->getAttribute("date");
      $title = $entry->getAttribute("title");
      $count = $entry->nodeValue;
      if(!isset($journalhistories[$title])) {
        $journalhistories[$title]=array();
      }
      array_push($journalhistories[$title],array("timestamp"=>$timestamp, "count"=>$count));
    }
  }
  print "Writing flot JS file $outputpath/flot.js\n";
  $flotjs = "";
  $flotjs2 = "\n$.plot($(\"#placeholder\"), [ ";
  $i=1;
  foreach($journalhistories as $key => $value) {
    $flotjs .= "var d$i = ";
    $flotjs2 .= "d$i, ";
    $journalhistory = $value;
    $flotjs .= "[";
    foreach ($journalhistory as $historypoint) {
      $flotjs .= "[".strtotime($historypoint["timestamp"]);
      $flotjs .= ", ".$historypoint["count"]."], ";
  //   $flotjs format is [[0, 3], [4, 8], [8, 5], [9, 13]];
  // [[x, y], [x, y], x y,
    }
    $flotjs .= "] \n";
    $i++;
  }
  $flotjs2 .= " ]);";  
  $fh = fopen("$outputpath/flot.js", 'w') or die("can't open file");
  fwrite($fh, $flotjs.$flotjs2);
  fclose($fh);
}
?>
