<?php
/**
 * author: Michael Chelen http://mikechelen.com http://twitter.com/mikechelen
 * license: Creative Commons Zero
 * downloads pmc open access subset ftp file list and computes article counts for each journal
 * source: ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt
 */
// file url
$url = "ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt";
// output path
$outputpath = "output";
// output file name
$outputfile = "output.xml";
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

  // get journal counts from source file
  echo "getting journal counts from $sourcefile \n";
  $result = getjournalcounts($sourcefile);

  // generate xml from source file
  makexml($sourcefile, $outputfile, $result["journals"], $result["timestamp"]);
}
// current souce file already exists
else {
  print "$sourcefile already exists, xml file untouched <a href=\"$outputfile\">$outputfile</a>";
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
    $timestamp = fgets($handle);
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
  $result["journals"] = $journals;
  $result["timestamp"] = $timestamp;
  return $result;
}
function makexml($sourcefile,$outputfile, $journals, $timestamp) {
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

  // load xml file if it exists
  if (file_exists($outputfile)) {
    appendxml($outputfile, $recordset);
  }
  else {
  // xml output file does not exist
    writenewxml($doc, outputfile);
  }
}
function appendxml($outputfile, $recordset) {
  //  load xml
    $olddoc = new DOMDocument();
    $olddoc->load($outputfile);
//    import new recordset to old xml
    $node = $olddoc->importNode($recordset, true);
//    make it pretty
    $node->formatOutput=true;
    $olddoc->documentElement->appendChild($node);
//    make it pretty
    $olddoc->formatOutput=true;
//    write XML file
    $fh = fopen($outputfile, 'w') or die("can't open file");
    fwrite($fh, $olddoc->saveXML());
    fclose($fh);
    print "appended data to xml file <a href=\"$outputfile\">$outputfile</a>";
}
function writenewxml($doc, $outputfile) {
  //  make it pretty
    $doc->formatOutput=true;
  //  write XML file
    $fh = fopen($outputfile, 'w') or die("can't open file");
    fwrite($fh, $doc->saveXML());
    fclose($fh);
    print "wrote new xml file <a href=\"$outputfile\">$outputfile</a>";
}
?>
