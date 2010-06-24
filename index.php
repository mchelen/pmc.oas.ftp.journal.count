<?php
/**
 * author: Michael Chelen http://mikechelen.com http://twitter.com/mikechelen
 * license: Creative Commons Zero http://creativecommons.org/publicdomain/zero/1.0/
 * downloads pmc open access subset ftp file list and computes article counts for each journal
 * source: ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt
 */

// get command line parameters
$shortopts = "r";
$longopts = array(
"rebuild", // no value
);
$options = getopt($shortopts,$longopts);
// var_dump($options);

// echo $_SERVER['REMOTE_ADDR'];

// if(webpage()) {print "<pre>";}
//else {echo "running in shell";}


// file url
$url = "ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt";
// output file path
$outputpath = "output";
// output file name
$outputfile = "$outputpath/output.xml";
// source file path
$sourcepath = "source";
// get the timestamp from the remote file
$timestamp = gettimestamp($url);
// generate source file name based on timestamp
$sourcefile = "$sourcepath/file_list.".preg_replace('/[^0-9]/',".",$timestamp).".txt";
// check if that file has been downloaded yet
if (!file_exists($sourcefile)) {
  // source file does not exist
  // download file
  downloadfile($sourcepath, $sourcefile, $url);
  // process newly downloaded file
  processfile($sourcefile,$outputfile,$outputpath);
}
elseif (isset($_GET['rebuild'])||isset($options["rebuild"])) {
  // process all files
  print "Rebuilding output from source files\n";
  
  // remove output file if it exists
  if(file_exists($outputfile)){
    print "Removing old $outputfile \n";
    $output = `rm $outputfile`;
  }
 
  // initialize array
  $dircontents = array();
  // read list of files in source directory
  if ($handle = opendir($sourcepath)) {
    while (false !== ($file = readdir($handle))) {
      if ($file != "." && $file != "..") {
        array_push($dircontents,"$sourcepath/$file");
      }
    }
  }
// run processfille on each file (in alphanumeric order"
  foreach ($dircontents as $dircontent) {
    processfile(array_pop($dircontents),$outputfile,$outputpath);
  }
}
// current source file already exists
else {
  if(webpage()) { print "<br />";}
  print "Current source file exists: $sourcefile \nXML output file untouched: ";
  if(webpage()) { print "<a href=\"$outputfile\">$outputfile</a>"; }
  else {print $outputfile;}
  print ("\n");
}


// generate some kind of display from output.xml
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

// [ d1, d2, d3 ]


$i=1;
foreach($journalhistories as $key => $value) {
  $flotjs .= "var d$i = ";
  $flotjs2 .= "d$i, ";
  
  $journalhistory = $value;
  
  $flotjs .= "[";
  
  foreach ($journalhistory as $historypoint) {
    $flotjs .= "[".strtotime($historypoint["timestamp"]);
    $flotjs .= ", ".$historypoint["count"]."], ";
//     $flotjs .= [[0, 3], [4, 8], [8, 5], [9, 13]];
  }
  $flotjs .= "] \n";
  
// [[0, 3], [4, 8], [8, 5], [9, 13]];
// x y, x y, x y,

  $i++;
}

$flotjs2 .= " ]);";  

$fh = fopen("$outputpath/flot.js", 'w') or die("can't open file");
fwrite($fh, $flotjs.$flotjs2);
fclose($fh);

// desired format: [[0, 3], [4, 8], [8, 5], [9, 13]];

// if(webpage()) {print "</pre>";}






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
?>
