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
makeflotjs($outputfile,$outputpath);

// if(webpage()) {print "</pre>";}

?>
