<?php
/**
 * author: Michael Chelen http://mikechelen.com http://twitter.com/mikechelen
 * license: Creative Commons Zero http://creativecommons.org/publicdomain/zero/1.0/
 * downloads pmc open access subset ftp file list and computes article counts for each journal
 * source: ftp://ftp.ncbi.nlm.nih.gov/pub/pmc/file_list.txt
 */

require_once("functions.php");

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
// if(webpage()) {print "</pre>";}

?>
