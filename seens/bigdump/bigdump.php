<?php

error_reporting(E_ALL);

// BigDump ver. 0.37b from 2023-09-25
// Staggered import of an large MySQL Dump (like phpMyAdmin 2.x Dump)
// Even through the webservers with hard runtime limit and those in safe mode
// Works fine with latest Chrome, Internet Explorer and Firefox

// Author:       Alexey Ozerov (alexey at ozerov dot de) 
//               AJAX & CSV functionalities: Krzysiek Herod (kr81uni at wp dot pl) 
// Copyright:    GPL (C) 2003-2023
// More Infos:   http://www.ozerov.de/bigdump

// This program is free software; you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software Foundation;
// either version 2 of the License, or (at your option) any later version.

// THIS SCRIPT IS PROVIDED AS IS, WITHOUT ANY WARRANTY OR GUARANTEE OF ANY KIND

// USAGE

// 1. Adjust the database configuration and charset in this file
// 2. Remove the old tables on the target database if your dump doesn't contain "DROP TABLE"
// 3. Create the working directory (e.g. dump) on your web server
// 4. Upload bigdump.php and your dump files (.sql, .gz) via FTP to the working directory
// 5. Run the bigdump.php from your browser via URL like http://www.yourdomain.com/dump/bigdump.php
// 6. BigDump can start the next import session automatically if you enable the JavaScript
// 7. Wait for the script to finish, do not close the browser window
// 8. IMPORTANT: Remove bigdump.php and your dump files from the web server

// If Timeout errors still occure you may need to adjust the $linepersession setting in this file

// LAST CHANGES

// *** PHP8 compatibility

// Database configuration

$db_server   = 'localhost';
$db_name     = 'seait_seens';
$db_username = 'root';
$db_password = ''; 

// Connection charset should be the same as the dump file charset (utf8, latin1, cp1251, koi8r etc.)
// See http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html for the full list
// Change this if you have problems with non-latin letters

$db_connection_charset = 'utf8';

// OPTIONAL SETTINGS 

$filename           = '';     // Specify the dump filename to suppress the file selection dialog
$ajax               = true;   // AJAX mode: import will be done without refreshing the website
$linespersession    = 3000;   // Lines to be executed per one import session
$delaypersession    = 0;      // You can specify a sleep time in milliseconds after each session
                              // Works only if JavaScript is activated. Use to reduce server overrun

// CSV related settings (only if you use a CSV dump)

$csv_insert_table   = '';     // Destination table for CSV files
$csv_preempty_table = false;  // true: delete all entries from table specified in $csv_insert_table before processing
$csv_delimiter      = ',';    // Field delimiter in CSV file
$csv_add_quotes     = true;   // If your CSV data already have quotes around each field set it to false
$csv_add_slashes    = true;   // If your CSV data already have slashes in front of ' and " set it to false

// Allowed comment markers: lines starting with these strings will be ignored by BigDump

$comment[]='#';                       // Standard comment lines are dropped by default
$comment[]='-- ';
$comment[]='DELIMITER';               // Ignore DELIMITER switch as it's not a valid SQL statement
// $comment[]='---';                  // Uncomment this line if using proprietary dump created by outdated mysqldump
// $comment[]='CREATE DATABASE';      // Uncomment this line if your dump contains create database queries in order to ignore them
$comment[]='/*!';                     // Or add your own string to leave out other proprietary things

// Pre-queries: SQL queries to be executed at the beginning of each import session

// $pre_query[]='SET foreign_key_checks = 0';
// $pre_query[]='Add additional queries if you want here';

// Default query delimiter: this character at the line end tells Bigdump where a SQL statement ends
// Can be changed by DELIMITER statement in the dump file (normally used when defining procedures/functions)

$delimiter = ';';

// String quotes character

$string_quotes = '\'';                  // Change to '"' if your dump file uses double qoutes for strings

// How many lines may be considered to be one query (except text lines)

$max_query_lines = 300;

// Where to put the upload files into (default: bigdump folder)

$upload_dir = dirname(__FILE__);

// *******************************************************************************************
// If not familiar with PHP please don't change anything below this line
// *******************************************************************************************

if ($ajax)
  ob_start();

define ('VERSION','0.37b');
define ('DATA_CHUNK_LENGTH',16384);  // How many chars are read per time
define ('TESTMODE',false);           // Set to true to process the file without actually accessing the database
define ('BIGDUMP_DIR',dirname(__FILE__));
define ('PLUGIN_DIR',BIGDUMP_DIR.'/plugins/');

header("Expires: Mon, 1 Dec 2003 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

@ini_set('auto_detect_line_endings', true);
@set_time_limit(0);

if (function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get"))
  @date_default_timezone_set(@date_default_timezone_get());

// Clean and strip anything we don't want from user's input [0.27b]

foreach ($_REQUEST as $key => $val) 
{
  $val = preg_replace("/[^_A-Za-z0-9-\.&= ;\$]/i",'', $val);
  $_REQUEST[$key] = $val;
}

// Plugin handling is still EXPERIMENTAL and DISABLED
// Register plugins by including plugin_name.php from each ./plugins/plugin_name
/*
if (is_dir(PLUGIN_DIR)) 
{ if ($dh = opendir(PLUGIN_DIR)) 
	{
    while (($file = readdir($dh)) !== false) 
    { if (is_dir(PLUGIN_DIR.$file) && $file!='.' && $file!='..' && file_exists(PLUGIN_DIR.$file.'/'.$file.'.php'))
       include (PLUGIN_DIR.$file.'/'.$file.'.php');
    }
    closedir($dh);
  }
}
*/

do_action('header');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BigDump: Staggered MySQL Dump Importer ver. <?php echo (VERSION); ?></title>
    <meta http-equiv="Cache-Control" content="no-cache"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="-1"/>
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'security': {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../assets/sweet-alert/sweetalert2.min.css">
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        /* Responsive improvements */
        @media (max-width: 640px) {
            .card-hover:hover {
                transform: translateY(-2px);
            }
        }
        
        /* Prevent horizontal overflow */
        body {
            overflow-x: hidden;
        }
        
        /* Ensure table responsiveness */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile-friendly button sizes */
        @media (max-width: 640px) {
            .btn-primary {
                min-height: 44px;
            }
        }
        
        /* Progress bar styling */
        .progress-bar {
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transition: width 0.3s ease;
        }
        
        /* Status indicators */
        .status-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .status-error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .status-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .status-info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
    </style>

<?php do_action('head_meta'); ?>
</head>

<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">
    <div class="max-w-7xl mx-auto p-4 sm:p-6">
        
        <!-- Header Section -->
        <div class="text-center mb-6 sm:mb-8 animate-fade-in">
            <!-- Back Button -->
            <div class="flex justify-between items-center mb-4">
                <a href="../index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-lg">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to SEENS
                </a>
                <div class="flex-1"></div>
            </div>
            
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-database mr-3 text-blue-600"></i>
                BigDump: Staggered MySQL Dump Importer
            </h1>
            <p class="text-sm sm:text-base md:text-lg text-gray-600 px-4">Version <?php echo (VERSION); ?></p>
            <div class="w-16 sm:w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto mt-4 rounded-full"></div>
        </div>
        
        <!-- Main Content Container -->
        <div class="bg-white rounded-xl shadow-lg p-6 sm:p-8 animate-slide-up">

<?php

function skin_open() 
{
  echo ('<div class="bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg p-4 mb-6 shadow-lg">');
}

function skin_close() 
{
  echo ('</div>');
}

// Only show header if not in import mode
if (!isset($_REQUEST["start"])) {
  skin_open();
  echo ('<h2 class="text-xl font-bold text-center"><i class="fas fa-database mr-2"></i>Database Import Tool</h2>');
  echo ('<p class="text-center text-blue-100 mt-2">Import large MySQL dump files safely and efficiently</p>');
  skin_close();
}

do_action('after_headline');

$error = false;
$file  = false;

// Check PHP version

if (!$error && !function_exists('version_compare'))
{ echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
    <i class=\"fas fa-exclamation-triangle mr-2\"></i>
    <span>PHP version 4.1.0 is required for BigDump to proceed. You have PHP ".phpversion()." installed. Sorry!</span>
  </div>\n");
  $error=true;
}

// Check if mysql extension is available

if (!$error && !function_exists('mysqli_connect'))
{ echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
    <i class=\"fas fa-exclamation-triangle mr-2\"></i>
    <span>There is no mySQLi extension found in your PHP installation. You can use an older Bigdump version if your PHP supports mySQL extension.</span>
  </div>\n");
  $error=true;
}

// Calculate PHP max upload size (handle settings like 10M or 100K)

if (!$error)
{ $upload_max_filesize=ini_get("upload_max_filesize");
  if (preg_match("/([0-9]+)K/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024;
  if (preg_match("/([0-9]+)M/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024;
  if (preg_match("/([0-9]+)G/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024*1024;
}

// Get the current directory
/*
if (isset($_SERVER["CGIA"]))
  $upload_dir=dirname($_SERVER["CGIA"]);
else if (isset($_SERVER["ORIG_PATH_TRANSLATED"]))
  $upload_dir=dirname($_SERVER["ORIG_PATH_TRANSLATED"]);
else if (isset($_SERVER["ORIG_SCRIPT_FILENAME"]))
  $upload_dir=dirname($_SERVER["ORIG_SCRIPT_FILENAME"]);
else if (isset($_SERVER["PATH_TRANSLATED"]))
  $upload_dir=dirname($_SERVER["PATH_TRANSLATED"]);
else 
  $upload_dir=dirname($_SERVER["SCRIPT_FILENAME"]);
*/
  
do_action ('script_runs');

// Handle file upload

if (!$error && isset($_REQUEST["uploadbutton"]))
{ if (is_uploaded_file($_FILES["dumpfile"]["tmp_name"]) && ($_FILES["dumpfile"]["error"])==0)
  { 
    $uploaded_filename=str_replace(" ","_",$_FILES["dumpfile"]["name"]);
    $uploaded_filename=preg_replace("/[^_A-Za-z0-9-\.]/i",'',$uploaded_filename);
    $uploaded_filepath=str_replace("\\","/",$upload_dir."/".$uploaded_filename);

    do_action('file_uploaded');

    if (file_exists($uploaded_filename))
    { echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
        <i class=\"fas fa-exclamation-triangle mr-2\"></i>
        <span>File $uploaded_filename already exists! Delete and upload again!</span>
      </div>\n");
    }
    else if (!preg_match("/(\.(sql|gz|csv))$/i",$uploaded_filename))
    { echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
        <i class=\"fas fa-exclamation-triangle mr-2\"></i>
        <span>You may only upload .sql .gz or .csv files.</span>
      </div>\n");
    }
    else if (!@move_uploaded_file($_FILES["dumpfile"]["tmp_name"],$uploaded_filepath))
    { echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4\">
        <div class=\"flex items-center mb-2\">
          <i class=\"fas fa-exclamation-triangle mr-2\"></i>
          <span class=\"font-semibold\">Upload Error</span>
        </div>
        <p class=\"text-sm\">Error moving uploaded file ".$_FILES["dumpfile"]["tmp_name"]." to the $uploaded_filepath</p>
        <p class=\"text-sm mt-1\">Check the directory permissions for $upload_dir (must be 777)!</p>
      </div>\n");
    }
    else
    { echo ("<div class=\"bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
        <i class=\"fas fa-check-circle mr-2\"></i>
        <span>Uploaded file saved as $uploaded_filename</span>
      </div>\n");
    }
  }
  else
  { echo ("<p class=\"error\">Error uploading file ".$_FILES["dumpfile"]["name"]."</p>\n");
  }
}


// Handle file deletion (delete only in the current directory for security reasons)

if (!$error && isset($_REQUEST["delete"]) && $_REQUEST["delete"]!=basename($_SERVER["SCRIPT_FILENAME"]))
{ if (preg_match("/(\.(sql|gz|csv))$/i",$_REQUEST["delete"]) && @unlink($upload_dir.'/'.$_REQUEST["delete"])) 
    echo ("<p class=\"success\">".$_REQUEST["delete"]." was removed successfully</p>\n");
  else
    echo ("<p class=\"error\">Can't remove ".$_REQUEST["delete"]."</p>\n");
}

// Connect to the database, set charset and execute pre-queries

if (!$error && !TESTMODE)
{ $mysqli = new mysqli($db_server, $db_username, $db_password, $db_name);
  
  if (mysqli_connect_error()) 
  { echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4\">
      <div class=\"flex items-center mb-2\">
        <i class=\"fas fa-exclamation-triangle mr-2\"></i>
        <span class=\"font-semibold\">Database connection failed</span>
      </div>
      <p class=\"text-sm\">Error: ".mysqli_connect_error()."</p>
      <p class=\"text-sm mt-1\">Edit the database settings in BigDump configuration, or contact your database provider.</p>
    </div>\n");
    $error=true;
  }
  if (!$error && $db_connection_charset!=='')
    $mysqli->query("SET NAMES $db_connection_charset");

  if (!$error)
    mysqli_report(MYSQLI_REPORT_OFF);

  if (!$error && isset ($pre_query) && sizeof ($pre_query)>0)
  { reset($pre_query);
    foreach ($pre_query as $pre_query_value)
    {	if (!$mysqli->query($pre_query_value))
    	{ echo ("<p class=\"error\">Error with pre-query.</p>\n");
      	echo ("<p>Query: ".trim(nl2br(htmlentities($pre_query_value)))."</p>\n");
      	echo ("<p>MySQL: ".$mysqli->error."</p>\n");
      	$error=true;
      	break;
     }
    }
  }
}
else
{ $dbconnection = false;
}

do_action('database_connected');

// DIAGNOSTIC
// echo("<h1>Checkpoint!</h1>");

// List uploaded files in multifile mode

if (!$error && !isset($_REQUEST["fn"]) && $filename=="")
{ if ($dirhandle = opendir($upload_dir)) 
  { 
    $files=array();
    while (false !== ($files[] = readdir($dirhandle)));
    closedir($dirhandle);
    $dirhead=false;

    if (sizeof($files)>0)
    { 
      sort($files);
      foreach ($files as $dirfile)
      { 
        if ($dirfile != "." && $dirfile != ".." && $dirfile!=basename($_SERVER["SCRIPT_FILENAME"]) && preg_match("/\.(sql|gz|csv)$/i",$dirfile))
        { if (!$dirhead)
          { echo ("<div class=\"bg-gray-50 rounded-lg p-4 mb-6\">
              <h3 class=\"text-lg font-semibold text-gray-800 mb-4 flex items-center\">
                <i class=\"fas fa-folder-open mr-2 text-blue-600\"></i>
                Available Import Files
              </h3>
              <div class=\"table-responsive\">
                <table class=\"w-full bg-white rounded-lg shadow-sm overflow-hidden\">
                  <thead class=\"bg-gradient-to-r from-blue-500 to-purple-500 text-white\">
                    <tr>
                      <th class=\"px-4 py-3 text-left font-semibold\">Filename</th>
                      <th class=\"px-4 py-3 text-right font-semibold\">Size</th>
                      <th class=\"px-4 py-3 text-left font-semibold\">Date &amp; Time</th>
                      <th class=\"px-4 py-3 text-center font-semibold\">Type</th>
                      <th class=\"px-4 py-3 text-center font-semibold\">Actions</th>
                    </tr>
                  </thead>
                  <tbody class=\"divide-y divide-gray-200\">\n");
            $dirhead=true;
          }
          
          $file_size = filesize($upload_dir.'/'.$dirfile);
          $file_size_formatted = $file_size > 1024*1024 ? round($file_size/1024/1024, 2).' MB' : round($file_size/1024, 2).' KB';
          
          echo ("<tr class=\"hover:bg-gray-50 transition-colors\">
            <td class=\"px-4 py-3 text-sm font-medium text-gray-900\">$dirfile</td>
            <td class=\"px-4 py-3 text-sm text-right text-gray-600\">$file_size_formatted</td>
            <td class=\"px-4 py-3 text-sm text-gray-600\">".date ("Y-m-d H:i:s", filemtime($upload_dir.'/'.$dirfile))."</td>");

          if (preg_match("/\.sql$/i",$dirfile))
            echo ("<td class=\"px-4 py-3 text-center\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800\">SQL</span></td>");
          elseif (preg_match("/\.gz$/i",$dirfile))
            echo ("<td class=\"px-4 py-3 text-center\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800\">GZip</span></td>");
          elseif (preg_match("/\.csv$/i",$dirfile))
            echo ("<td class=\"px-4 py-3 text-center\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800\">CSV</span></td>");
          else
            echo ("<td class=\"px-4 py-3 text-center\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800\">Misc</span></td>");

          if ((preg_match("/\.gz$/i",$dirfile) && function_exists("gzopen")) || preg_match("/\.sql$/i",$dirfile) || preg_match("/\.csv$/i",$dirfile))
            echo ("<td class=\"px-4 py-3 text-center space-x-2\">
              <a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($dirfile)."&amp;foffset=0&amp;totalqueries=0&amp;delimiter=".urlencode($delimiter)."\" class=\"inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200\">
                <i class=\"fas fa-play mr-1\"></i>Start Import
              </a>
              <a href=\"".$_SERVER["PHP_SELF"]."?delete=".urlencode($dirfile)."\" class=\"inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-500 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200\" onclick=\"return confirm('Are you sure you want to delete this file?')\">
                <i class=\"fas fa-trash mr-1\"></i>Delete
              </a>
            </td></tr>\n");
          else
            echo ("<td class=\"px-4 py-3 text-center text-gray-400\">Not supported</td></tr>\n");
        }
      }
    }

    if ($dirhead) 
      echo ("</tbody></table></div></div>\n");
    else 
      echo ("<div class=\"bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6\">
        <div class=\"flex items-center\">
          <i class=\"fas fa-exclamation-triangle text-yellow-600 mr-2\"></i>
          <span class=\"text-yellow-800\">No uploaded SQL, GZ or CSV files found in the working directory</span>
        </div>
      </div>\n");
  }
  else
  { echo ("<p class=\"error\">Error listing directory $upload_dir</p>\n");
    $error=true;
  }
}


// Single file mode

if (!$error && !isset ($_REQUEST["fn"]) && $filename!="")
{ echo ("<p><a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($filename)."&amp;foffset=0&amp;totalqueries=0\">Start Import</a> from $filename into $db_name at $db_server</p>\n");
}


// File Upload Form

if (!$error && !isset($_REQUEST["fn"]) && $filename=="")
{ 

// Test permissions on working directory

  do { $tempfilename=$upload_dir.'/'.time().".tmp"; } while (file_exists($tempfilename)); 
  if (!($tempfile=@fopen($tempfilename,"w")))
  { echo ("<p>Upload form disabled. Permissions for the working directory <i>$upload_dir</i> <b>must be set writable for the webserver</b> in order ");
    echo ("to upload files here. Or you can upload your dump files via FTP.</p>\n");
  }
  else
  { fclose($tempfile);
    unlink ($tempfilename);
 
    echo ("<div class=\"bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6\">
      <div class=\"flex items-center mb-3\">
        <i class=\"fas fa-info-circle text-blue-600 mr-2\"></i>
        <span class=\"text-blue-800 font-medium\">File Upload Information</span>
      </div>
      <p class=\"text-blue-700 text-sm\">You can now upload your dump file up to $upload_max_filesize bytes (".round ($upload_max_filesize/1024/1024)." Mbytes) directly from your browser to the server. Alternatively you can upload your dump files of any size via FTP.</p>
    </div>\n");
?>
<div class="bg-white border-2 border-dashed border-gray-300 rounded-lg p-6 mb-6 hover:border-blue-400 transition-colors">
  <form method="POST" action="<?php echo ($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="MAX_FILE_SIZE" value="$upload_max_filesize">
    
    <div class="text-center">
      <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
      <h3 class="text-lg font-medium text-gray-900 mb-2">Upload Database Dump File</h3>
      <p class="text-sm text-gray-600 mb-4">Select a .sql, .gz, or .csv file to import</p>
    </div>
    
    <div class="flex items-center justify-center w-full">
      <label for="dumpfile" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
        <div class="flex flex-col items-center justify-center pt-5 pb-6">
          <i class="fas fa-file-upload text-2xl text-gray-400 mb-2"></i>
          <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
          <p class="text-xs text-gray-500">SQL, GZ, CSV files only</p>
        </div>
        <input id="dumpfile" name="dumpfile" type="file" class="hidden" accept=".sql,.gz,.csv" />
      </label>
    </div>
    
    <div class="flex justify-center">
      <button type="submit" name="uploadbutton" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg">
        <i class="fas fa-upload mr-2"></i>
        Upload File
      </button>
    </div>
  </form>
</div>
<?php
  }
}

// Print the current mySQL connection charset

if (!$error && !TESTMODE && !isset($_REQUEST["fn"]))
{ 
  $result = $mysqli->query("SHOW VARIABLES LIKE 'character_set_connection';");
  if ($result) 
  { $row = $result->fetch_assoc();
    if ($row) 
    { $charset = $row['Value'];
      echo ("<p>Note: The current mySQL connection charset is <i>$charset</i>. Your dump file must be encoded in <i>$charset</i> in order to avoid problems with non-latin characters. You can change the connection charset using the \$db_connection_charset variable in bigdump.php</p>\n");
    }
    $result->free();
  }
}

// Open the file

if (!$error && isset($_REQUEST["start"]))
{ 

// Set current filename ($filename overrides $_REQUEST["fn"] if set)

  if ($filename!="")
    $curfilename=$filename;
  else if (isset($_REQUEST["fn"]))
    $curfilename=urldecode($_REQUEST["fn"]);
  else
    $curfilename="";

// Recognize GZip filename

  if (preg_match("/\.gz$/i",$curfilename)) 
    $gzipmode=true;
  else
    $gzipmode=false;

  if ((!$gzipmode && !$file=@fopen($upload_dir.'/'.$curfilename,"r")) || ($gzipmode && !$file=@gzopen($upload_dir.'/'.$curfilename,"r")))
  { echo ("<p class=\"error\">Can't open ".$curfilename." for import</p>\n");
    echo ("<p>Please, check that your dump file name contains only alphanumerical characters, and rename it accordingly, for example: $curfilename.".
           "<br>Or, specify \$filename in bigdump.php with the full filename. ".
           "<br>Or, you have to upload the $curfilename to the server first.</p>\n");
    $error=true;
  }

// Get the file size (can't do it fast on gzipped files, no idea how)

  else if ((!$gzipmode && @fseek($file, 0, SEEK_END)==0) || ($gzipmode && @gzseek($file, 0)==0))
  { if (!$gzipmode) $filesize = ftell($file);
    else $filesize = gztell($file);                   // Always zero, ignore
  }
  else
  { echo ("<p class=\"error\">I can't seek into $curfilename</p>\n");
    $error=true;
  }

// Stop if csv file is used, but $csv_insert_table is not set

  if (!$error && ($csv_insert_table == "") && (preg_match("/(\.csv)$/i",$curfilename)))
  { echo ("<p class=\"error\">You have to specify \$csv_insert_table when using a CSV file. </p>\n");
    $error=true;
  }
}


// *******************************************************************************************
// START IMPORT SESSION HERE
// *******************************************************************************************

if (!$error && isset($_REQUEST["start"]) && isset($_REQUEST["foffset"]) && preg_match("/(\.(sql|gz|csv))$/i",$curfilename))
{

  do_action('session_start');

// Check start and foffset are numeric values

  if (!is_numeric($_REQUEST["start"]) || !is_numeric($_REQUEST["foffset"]))
  { echo ("<p class=\"error\">UNEXPECTED: Non-numeric values for start and foffset</p>\n");
    $error=true;
  }
  else
  {	$_REQUEST["start"]   = floor($_REQUEST["start"]);
    $_REQUEST["foffset"] = floor($_REQUEST["foffset"]);
  }

// Set the current delimiter if defined

  if (isset($_REQUEST["delimiter"]))
    $delimiter = $_REQUEST["delimiter"];

// Empty CSV table if requested

  if (!$error && $_REQUEST["start"]==1 && $csv_insert_table != "" && $csv_preempty_table)
  { 
    $query = "DELETE FROM `$csv_insert_table`";
    if (!TESTMODE && !$mysqli->query(trim($query)))
    { echo ("<p class=\"error\">Error when deleting entries from $csv_insert_table.</p>\n");
      echo ("<p>Query: ".trim(nl2br(htmlentities($query)))."</p>\n");
      echo ("<p>MySQL: ".$mysqli->error."</p>\n");
      $error=true;
    }
  }
  
// Print start message

  if (!$error)
  { skin_open();
    if (TESTMODE) 
      echo ("<p class=\"centr\">TEST MODE ENABLED</p>\n");
    echo ("<p class=\"centr\">Processing file: <b>".$curfilename."</b></p>\n");
    echo ("<p class=\"smlcentr\">Starting from line: ".$_REQUEST["start"]."</p>\n");	
    skin_close();
  }

// Check $_REQUEST["foffset"] upon $filesize (can't do it on gzipped files)

  if (!$error && !$gzipmode && $_REQUEST["foffset"]>$filesize)
  { echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer behind the end of file</p>\n");
    $error=true;
  }

// Set file pointer to $_REQUEST["foffset"]

  if (!$error && ((!$gzipmode && fseek($file, $_REQUEST["foffset"])!=0) || ($gzipmode && gzseek($file, $_REQUEST["foffset"])!=0)))
  { echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer to offset: ".$_REQUEST["foffset"]."</p>\n");
    $error=true;
  }

// Start processing queries from $file

  if (!$error)
  { $query="";
    $queries=0;
    $totalqueries=$_REQUEST["totalqueries"];
    $linenumber=$_REQUEST["start"];
    $querylines=0;
    $inparents=false;

// Stay processing as long as the $linespersession is not reached or the query is still incomplete

    while ($linenumber<$_REQUEST["start"]+$linespersession || $query!="")
    {

// Read the whole next line

      $dumpline = "";
      while (!feof($file) && substr ($dumpline, -1) != "\n" && substr ($dumpline, -1) != "\r")
      { if (!$gzipmode)
          $dumpline .= fgets($file, DATA_CHUNK_LENGTH);
        else
          $dumpline .= gzgets($file, DATA_CHUNK_LENGTH);
      }
      if ($dumpline==="") break;

// Remove UTF8 Byte Order Mark at the file beginning if any

      if ($_REQUEST["foffset"]==0)
        $dumpline=preg_replace('|^\xEF\xBB\xBF|','',$dumpline);

// Create an SQL query from CSV line

      if (($csv_insert_table != "") && (preg_match("/(\.csv)$/i",$curfilename)))
      {
        if ($csv_add_slashes)
          $dumpline = addslashes($dumpline);
        $dumpline = explode($csv_delimiter,$dumpline);
        if ($csv_add_quotes)
          $dumpline = "'".implode("','",$dumpline)."'";
        else
          $dumpline = implode(",",$dumpline);
        $dumpline = 'INSERT INTO '.$csv_insert_table.' VALUES ('.$dumpline.');';
      }

// Handle DOS and Mac encoded linebreaks (I don't know if it really works on Win32 or Mac Servers)

      $dumpline=str_replace("\r\n", "\n", $dumpline);
      $dumpline=str_replace("\r", "\n", $dumpline);
            
// DIAGNOSTIC
// echo ("<p>Line $linenumber: $dumpline</p>\n");

// Recognize delimiter statement

      if (!$inparents && strpos ($dumpline, "DELIMITER ") === 0)
        $delimiter = str_replace ("DELIMITER ","",trim($dumpline));

// Skip comments and blank lines only if NOT in parents

      if (!$inparents)
      { $skipline=false;
        reset($comment);
        foreach ($comment as $comment_value)
        { 

// DIAGNOSTIC
//          echo ($comment_value);
          if (trim($dumpline)=="" || strpos (trim($dumpline), $comment_value) === 0)
          { $skipline=true;
            break;
          }
        }
        if ($skipline)
        { $linenumber++;

// DIAGNOSTIC
// echo ("<p>Comment line skipped</p>\n");

          continue;
        }
      }

// Remove double back-slashes from the dumpline prior to count the quotes ('\\' can only be within strings)
      
      $dumpline_deslashed = str_replace ("\\\\","",$dumpline);

// Count ' and \' (or " and \") in the dumpline to avoid query break within a text field ending by $delimiter

      $parents=substr_count ($dumpline_deslashed, $string_quotes)-substr_count ($dumpline_deslashed, "\\$string_quotes");
      if ($parents % 2 != 0)
        $inparents=!$inparents;

// Add the line to query

      $query .= $dumpline;

// Don't count the line if in parents (text fields may include unlimited linebreaks)
      
      if (!$inparents)
        $querylines++;
      
// Stop if query contains more lines as defined by $max_query_lines

      if ($querylines>$max_query_lines)
      {
        echo ("<p class=\"error\">Stopped at the line $linenumber. </p>");
        echo ("<p>At this place the current query includes more than ".$max_query_lines." dump lines. That can happen if your dump file was ");
        echo ("created by some tool which doesn't place a semicolon followed by a linebreak at the end of each query, or if your dump contains ");
        echo ("extended inserts or very long procedure definitions. Please read the <a href=\"http://www.ozerov.de/bigdump/usage/\">BigDump usage notes</a> ");
        echo ("for more infos. Ask for our support services ");
        echo ("in order to handle dump files containing extended inserts.</p>\n");
        $error=true;
        break;
      }

// Execute query if end of query detected ($delimiter as last character) AND NOT in parents

// DIAGNOSTIC
// echo ("<p>Regex: ".'/'.preg_quote($delimiter).'$/'."</p>\n");
// echo ("<p>In Parents: ".($inparents?"true":"false")."</p>\n");
// echo ("<p>Line: $dumpline</p>\n");

      if ((preg_match('/'.preg_quote($delimiter,'/').'$/',trim($dumpline)) || $delimiter=='') && !$inparents)
      { 

// Cut off delimiter of the end of the query

        $query = substr(trim($query),0,-1*strlen($delimiter));

// DIAGNOSTIC
// echo ("<p>Query: ".trim(nl2br(htmlentities($query)))."</p>\n");

        if (!TESTMODE && !$mysqli->query($query))
        { echo ("<p class=\"error\">Error at the line $linenumber: ". trim($dumpline)."</p>\n");
          echo ("<p>Query: ".trim(nl2br(htmlentities($query)))."</p>\n");
          echo ("<p>MySQL: ".$mysqli->error."</p>\n");
          $error=true;
          break;
        }
        $totalqueries++;
        $queries++;
        $query="";
        $querylines=0;
      }
      $linenumber++;
    }
  }

// Get the current file position

  if (!$error)
  { if (!$gzipmode) 
      $foffset = ftell($file);
    else
      $foffset = gztell($file);
    if (!$foffset)
    { echo ("<p class=\"error\">UNEXPECTED: Can't read the file pointer offset</p>\n");
      $error=true;
    }
  }

// Print statistics

skin_open();

// echo ("<p class=\"centr\"><b>Statistics</b></p>\n");

  if (!$error)
  { 
    $lines_this   = $linenumber-$_REQUEST["start"];
    $lines_done   = $linenumber-1;
    $lines_togo   = ' ? ';
    $lines_tota   = ' ? ';
    
    $queries_this = $queries;
    $queries_done = $totalqueries;
    $queries_togo = ' ? ';
    $queries_tota = ' ? ';

    $bytes_this   = $foffset-$_REQUEST["foffset"];
    $bytes_done   = $foffset;
    $kbytes_this  = round($bytes_this/1024,2);
    $kbytes_done  = round($bytes_done/1024,2);
    $mbytes_this  = round($kbytes_this/1024,2);
    $mbytes_done  = round($kbytes_done/1024,2);
   
    if (!$gzipmode)
    {
      $bytes_togo  = $filesize-$foffset;
      $bytes_tota  = $filesize;
      $kbytes_togo = round($bytes_togo/1024,2);
      $kbytes_tota = round($bytes_tota/1024,2);
      $mbytes_togo = round($kbytes_togo/1024,2);
      $mbytes_tota = round($kbytes_tota/1024,2);
      
      $pct_this   = ceil($bytes_this/$filesize*100);
      $pct_done   = ceil($foffset/$filesize*100);
      $pct_togo   = 100 - $pct_done;
      $pct_tota   = 100;

      if ($bytes_togo==0) 
      { $lines_togo   = '0'; 
        $lines_tota   = $linenumber-1; 
        $queries_togo = '0'; 
        $queries_tota = $totalqueries; 
      }

      $pct_bar    = "<div style=\"height:15px;width:$pct_done%;background-color:#000080;margin:0px;\"></div>";
    }
    else
    {
      $bytes_togo  = ' ? ';
      $bytes_tota  = ' ? ';
      $kbytes_togo = ' ? ';
      $kbytes_tota = ' ? ';
      $mbytes_togo = ' ? ';
      $mbytes_tota = ' ? ';
      
      $pct_this    = ' ? ';
      $pct_done    = ' ? ';
      $pct_togo    = ' ? ';
      $pct_tota    = 100;
      $pct_bar     = str_replace(' ','&nbsp;','<tt>[         Not available for gzipped files          ]</tt>');
    }
    
    echo ("
    <div class=\"bg-white rounded-lg shadow-lg p-6 mb-6\">
      <h3 class=\"text-lg font-semibold text-gray-800 mb-4 flex items-center\">
        <i class=\"fas fa-chart-bar mr-2 text-blue-600\"></i>
        Import Progress Statistics
      </h3>
      
      <div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6\">
        <div class=\"bg-blue-50 rounded-lg p-4 text-center\">
          <div class=\"text-2xl font-bold text-blue-600\">$lines_this</div>
          <div class=\"text-sm text-blue-800\">Lines This Session</div>
        </div>
        <div class=\"bg-green-50 rounded-lg p-4 text-center\">
          <div class=\"text-2xl font-bold text-green-600\">$queries_this</div>
          <div class=\"text-sm text-green-800\">Queries This Session</div>
        </div>
        <div class=\"bg-purple-50 rounded-lg p-4 text-center\">
          <div class=\"text-2xl font-bold text-purple-600\">$mbytes_this MB</div>
          <div class=\"text-sm text-purple-800\">Data This Session</div>
        </div>
        <div class=\"bg-orange-50 rounded-lg p-4 text-center\">
          <div class=\"text-2xl font-bold text-orange-600\">$pct_done%</div>
          <div class=\"text-sm text-orange-800\">Overall Progress</div>
        </div>
      </div>
      
      <div class=\"bg-gray-50 rounded-lg p-4\">
        <h4 class=\"text-md font-semibold text-gray-700 mb-3\">Detailed Statistics</h4>
        <div class=\"overflow-x-auto\">
          <table class=\"w-full text-sm\">
            <thead class=\"bg-gray-100\">
              <tr>
                <th class=\"px-3 py-2 text-left font-medium text-gray-700\">Metric</th>
                <th class=\"px-3 py-2 text-right font-medium text-gray-700\">Session</th>
                <th class=\"px-3 py-2 text-right font-medium text-gray-700\">Done</th>
                <th class=\"px-3 py-2 text-right font-medium text-gray-700\">To Go</th>
                <th class=\"px-3 py-2 text-right font-medium text-gray-700\">Total</th>
              </tr>
            </thead>
            <tbody class=\"divide-y divide-gray-200\">
              <tr>
                <td class=\"px-3 py-2 font-medium text-gray-700\">Lines</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$lines_this</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$lines_done</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$lines_togo</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$lines_tota</td>
              </tr>
              <tr>
                <td class=\"px-3 py-2 font-medium text-gray-700\">Queries</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$queries_this</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$queries_done</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$queries_togo</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$queries_tota</td>
              </tr>
              <tr>
                <td class=\"px-3 py-2 font-medium text-gray-700\">Data (MB)</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$mbytes_this</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$mbytes_done</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$mbytes_togo</td>
                <td class=\"px-3 py-2 text-right text-gray-600\">$mbytes_tota</td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <div class=\"mt-4\">
          <div class=\"flex items-center justify-between mb-2\">
            <span class=\"text-sm font-medium text-gray-700\">Progress</span>
            <span class=\"text-sm text-gray-600\">$pct_done%</span>
          </div>
          <div class=\"w-full bg-gray-200 rounded-full h-3\">
            <div class=\"progress-bar h-3 rounded-full\" style=\"width: $pct_done%\"></div>
          </div>
        </div>
      </div>
    </div>
    \n");

// Finish message and restart the script

    if ($linenumber<$_REQUEST["start"]+$linespersession)
    { echo ("<div class=\"bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-6 text-center\">
        <div class=\"flex items-center justify-center mb-2\">
          <i class=\"fas fa-check-circle text-2xl mr-2\"></i>
          <h3 class=\"text-lg font-bold\">Import Completed Successfully!</h3>
        </div>
        <p class=\"text-sm mb-2\">Congratulations: End of file reached, assuming OK</p>
        <div class=\"bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded-lg mt-3\">
          <div class=\"flex items-center justify-center\">
            <i class=\"fas fa-exclamation-triangle mr-2\"></i>
            <span class=\"font-semibold\">IMPORTANT: REMOVE YOUR DUMP FILE and BIGDUMP SCRIPT FROM SERVER NOW!</span>
          </div>
        </div>
      </div>\n");
      
      echo ("<div class=\"bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-center\">
        <p class=\"text-blue-800 mb-2\">Thank you for using this tool! Please rate <a href=\"http://www.hotscripts.com/listing/bigdump/?RID=403\" target=\"_blank\" class=\"text-blue-600 hover:text-blue-800 underline\">Bigdump at Hotscripts.com</a></p>
        <p class=\"text-blue-700 text-sm mb-3\">You can send some bucks or euros as appreciation via PayPal. Thank you!</p>
        <a href=\"../index.php\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200\">
          <i class=\"fas fa-arrow-left mr-2\"></i>Back to SEENS
        </a>
      </div>\n");
?>

<!-- Start Paypal donation code -->
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_xclick" />
<input type="hidden" name="business" value="info@fully-kiosk.com" />
<input type="hidden" name="item_name" value="BigDump Donation" />
<input type="hidden" name="no_shipping" value="1" />
<input type="hidden" name="no_note" value="0" />
<input type="hidden" name="tax" value="0" />
<input type="hidden" name="bn" value="PP-DonationsBF" />
<input type="hidden" name="lc" value="US" />
<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>
<!-- End Paypal donation code -->

<?php      
      do_action('script_finished');
      $error=true; // This is a semi-error telling the script is finished
    }
    else
    { if ($delaypersession!=0)
        echo ("<div class=\"bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-center\">
          <div class=\"flex items-center justify-center\">
            <i class=\"fas fa-clock text-blue-600 mr-2\"></i>
            <span class=\"text-blue-800\">Now I'm <b>waiting $delaypersession milliseconds</b> before starting next session...</span>
          </div>
        </div>\n");
      if (!$ajax) 
        echo ("<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&fn=".urlencode($curfilename)."&foffset=$foffset&totalqueries=$totalqueries&delimiter=".urlencode($delimiter)."\";',500+$delaypersession);</script>\n");

      echo ("<noscript>\n");
      echo ("<div class=\"bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-center\">
        <p class=\"text-yellow-800 mb-2\"><a href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries&amp;delimiter=".urlencode($delimiter)."\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200\">
          <i class=\"fas fa-play mr-2\"></i>Continue from line $linenumber
        </a></p>
        <p class=\"text-yellow-700 text-sm\">(Enable JavaScript to do it automatically)</p>
      </div>\n");
      echo ("</noscript>\n");
   
      echo ("<div class=\"bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 text-center\">
        <div class=\"flex flex-col sm:flex-row gap-3 justify-center items-center\">
          <p class=\"text-gray-700\">Press <b><a href=\"".$_SERVER["PHP_SELF"]."\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-500 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200\">
            <i class=\"fas fa-stop mr-2\"></i>STOP
          </a></b> to abort the import <b>OR WAIT!</b></p>
          <a href=\"../index.php\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200\">
            <i class=\"fas fa-arrow-left mr-2\"></i>Back to SEENS
          </a>
        </div>
      </div>\n");
    }
  }
  else 
    echo ("<div class=\"bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center\">
      <i class=\"fas fa-exclamation-triangle mr-2\"></i>
      <span class=\"font-semibold\">Stopped on error</span>
    </div>\n");

skin_close();

}

if ($error)
  echo ("<div class=\"bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 text-center\">
    <div class=\"flex flex-col sm:flex-row gap-3 justify-center items-center\">
      <a href=\"".$_SERVER["PHP_SELF"]."\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200\">
        <i class=\"fas fa-home mr-2\"></i>Start from the beginning
      </a>
      <a href=\"../index.php\" class=\"inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200\">
        <i class=\"fas fa-arrow-left mr-2\"></i>Back to SEENS
      </a>
    </div>
    <p class=\"text-gray-600 text-sm mt-2\">(DROP the old tables before restarting)</p>
  </div>\n");

if ($mysqli) $mysqli->close();
if ($file && !$gzipmode) fclose($file);
else if ($file && $gzipmode) gzclose($file);

?>

        </div> <!-- End Main Content Container -->
        
        <!-- Footer -->
        <div class="text-center mt-8 pb-6">
          <div class="bg-white rounded-lg shadow-lg p-4 inline-block">
            <p class="text-gray-600 text-sm">
              &copy; 2003-2023 <a href="mailto:alexey@ozerov.de" class="text-blue-600 hover:text-blue-800 underline">Alexey Ozerov</a> | 
              BigDump v<?php echo (VERSION); ?> | 
              <a href="http://www.ozerov.de/bigdump" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Official Website</a>
            </p>
          </div>
        </div>
      </div> <!-- End max-w-7xl container -->
<?php do_action('end_of_body'); ?>

    <!-- JavaScript for enhanced UX -->
    <script>
      // File upload preview
      document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('dumpfile');
        const uploadLabel = document.querySelector('label[for="dumpfile"]');
        
        if (fileInput && uploadLabel) {
          fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
              const fileName = file.name;
              const fileSize = (file.size / 1024 / 1024).toFixed(2);
              
              uploadLabel.innerHTML = `
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                  <i class="fas fa-file-check text-2xl text-green-500 mb-2"></i>
                  <p class="mb-2 text-sm text-gray-700"><span class="font-semibold">${fileName}</span></p>
                  <p class="text-xs text-gray-500">${fileSize} MB</p>
                </div>
              `;
            }
          });
        }
        
        // Smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
          anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
              target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
              });
            }
          });
        });
        
        // Add loading states to buttons
        document.querySelectorAll('button[type="submit"]').forEach(button => {
          button.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            this.disabled = true;
          });
        });
      });
    </script>
  </body>
</html>

<?php

// If error or finished put out the whole output from above and stop

if ($error) 
{
  $out1 = ob_get_contents();
  ob_end_clean();
  echo $out1;
  die;
}

// If Ajax enabled and in import progress creates responses  (XML response or script for the initial page)

if ($ajax && isset($_REQUEST['start']))
{
  if (isset($_REQUEST['ajaxrequest'])) 
  {	ob_end_clean();
	  create_xml_response();
	  die;
  } 
  else 
    create_ajax_script();	  
}

// Anyway put out the output from above

ob_flush();

// THE MAIN SCRIPT ENDS HERE

// *******************************************************************************************
// Plugin handling (EXPERIMENTAL)
// *******************************************************************************************

function do_action($tag)
{ global $plugin_actions;
  
  if (isset($plugin_actions[$tag]))
  { reset ($plugin_actions[$tag]);
    foreach ($plugin_actions[$tag] as $action)
      call_user_func_array($action, array());
  }
}

function add_action($tag, $function)
{
	global $plugin_actions;
	$plugin_actions[$tag][] = $function;
}

// *******************************************************************************************
// 				AJAX utilities
// *******************************************************************************************

function create_xml_response() 
{
  global $linenumber, $foffset, $totalqueries, $curfilename, $delimiter,
				 $lines_this, $lines_done, $lines_togo, $lines_tota,
				 $queries_this, $queries_done, $queries_togo, $queries_tota,
				 $bytes_this, $bytes_done, $bytes_togo, $bytes_tota,
				 $kbytes_this, $kbytes_done, $kbytes_togo, $kbytes_tota,
				 $mbytes_this, $mbytes_done, $mbytes_togo, $mbytes_tota,
				 $pct_this, $pct_done, $pct_togo, $pct_tota,$pct_bar;

	header('Content-Type: application/xml');
	header('Cache-Control: no-cache');
	
	echo '<?xml version="1.0" encoding="ISO-8859-1"?>';
	echo "<root>";

// data - for calculations

	echo "<linenumber>$linenumber</linenumber>";
	echo "<foffset>$foffset</foffset>";
	echo "<fn>$curfilename</fn>";
	echo "<totalqueries>$totalqueries</totalqueries>";
	echo "<delimiter>$delimiter</delimiter>";

// results - for page update

	echo "<elem1>$lines_this</elem1>";
	echo "<elem2>$lines_done</elem2>";
	echo "<elem3>$lines_togo</elem3>";
	echo "<elem4>$lines_tota</elem4>";
	
	echo "<elem5>$queries_this</elem5>";
	echo "<elem6>$queries_done</elem6>";
	echo "<elem7>$queries_togo</elem7>";
	echo "<elem8>$queries_tota</elem8>";
	
	echo "<elem9>$bytes_this</elem9>";
	echo "<elem10>$bytes_done</elem10>";
	echo "<elem11>$bytes_togo</elem11>";
	echo "<elem12>$bytes_tota</elem12>";
			
	echo "<elem13>$kbytes_this</elem13>";
	echo "<elem14>$kbytes_done</elem14>";
	echo "<elem15>$kbytes_togo</elem15>";
	echo "<elem16>$kbytes_tota</elem16>";
	
	echo "<elem17>$mbytes_this</elem17>";
	echo "<elem18>$mbytes_done</elem18>";
	echo "<elem19>$mbytes_togo</elem19>";
	echo "<elem20>$mbytes_tota</elem20>";
	
	echo "<elem21>$pct_this</elem21>";
	echo "<elem22>$pct_done</elem22>";
	echo "<elem23>$pct_togo</elem23>";
	echo "<elem24>$pct_tota</elem24>";
	echo "<elem_bar>".htmlentities($pct_bar)."</elem_bar>";
				
	echo "</root>";		
}


function create_ajax_script() 
{
  global $linenumber, $foffset, $totalqueries, $delaypersession, $curfilename, $delimiter;
?>

	<script type="text/javascript" language="javascript">			

	// creates next action url (upload page, or XML response)
	function get_url(linenumber,fn,foffset,totalqueries,delimiter) {
		return "<?php echo $_SERVER['PHP_SELF'] ?>?start="+linenumber+"&fn="+fn+"&foffset="+foffset+"&totalqueries="+totalqueries+"&delimiter="+delimiter+"&ajaxrequest=true";
	}
	
	// extracts text from XML element (itemname must be unique)
	function get_xml_data(itemname,xmld) {
		return xmld.getElementsByTagName(itemname).item(0).firstChild.data;
	}
	
	function makeRequest(url) {
		http_request = false;
		if (window.XMLHttpRequest) { 
		// Mozilla etc.
			http_request = new XMLHttpRequest();
			if (http_request.overrideMimeType) {
				http_request.overrideMimeType("text/xml");
			}
		} else if (window.ActiveXObject) { 
		// IE
			try {
				http_request = new ActiveXObject("Msxml2.XMLHTTP");
			} catch(e) {
				try {
					http_request = new ActiveXObject("Microsoft.XMLHTTP");
				} catch(e) {}
			}
		}
		if (!http_request) {
				alert("Cannot create an XMLHTTP instance");
				return false;
		}
		http_request.onreadystatechange = server_response;
		http_request.open("GET", url, true);
		http_request.send(null);
	}
	
	function server_response() 
	{

	  // waiting for correct response
	  if (http_request.readyState != 4)
		return;

	  if (http_request.status != 200) 
	  {
	    alert("Page unavailable, or wrong url!")
	    return;
	  }
		
		// r = xml response
		var r = http_request.responseXML;
		
		//if received not XML but HTML with new page to show
		if (!r || r.getElementsByTagName('root').length == 0) 
		{	var text = http_request.responseText;
			document.open();
			document.write(text);		
			document.close();	
			return;		
		}
		
		// update "Starting from line: "
		document.getElementsByTagName('p').item(1).innerHTML = 
			"Starting from line: " + 
			   r.getElementsByTagName('linenumber').item(0).firstChild.nodeValue;
		
		// update table with new values
		for(i = 1; i <= 24; i++)
			document.getElementsByTagName('td').item(i).firstChild.data = get_xml_data('elem'+i,r);
		
		// update color bar
		document.getElementsByTagName('td').item(25).innerHTML = 
			r.getElementsByTagName('elem_bar').item(0).firstChild.nodeValue;
			 
		// action url (XML response)	 
		url_request =  get_url(
			get_xml_data('linenumber',r),
			get_xml_data('fn',r),
			get_xml_data('foffset',r),
			get_xml_data('totalqueries',r),
			get_xml_data('delimiter',r));
		
		// ask for XML response	
		window.setTimeout("makeRequest(url_request)",500+<?php echo $delaypersession; ?>);
	}

	// First Ajax request from initial page

	var http_request = false;
	var url_request =  get_url(<?php echo ($linenumber.',"'.urlencode($curfilename).'",'.$foffset.','.$totalqueries.',"'.urlencode($delimiter).'"') ;?>);
	window.setTimeout("makeRequest(url_request)",500+<?php echo $delaypersession; ?>);
	</script>

<?php
}

?>
