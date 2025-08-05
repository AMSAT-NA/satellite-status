<?php date_default_timezone_set('UTC'); ?>
<html>
<body>
<?php
 // Include files
include("config.php");

const PARSE_GRID_SQUARE_FROM_CALLSIGN = true;

$submit=$_REQUEST["SatSubmit"];
if ($submit)
{

 $SatName=getFormData("SatName");
 $SatReport=getFormData("SatReport", "");
 $SatMonth=getFormData("SatMonth");
 $SatDay=getFormData("SatDay");
 $SatHour=getFormData("SatHour");
 $SatYear=getFormData("SatYear");
 $SatPeriod=getFormData("SatPeriod");
 $SatCall=trim(getFormData("SatCall"));
 $SatGridSquare = standardizedGridSquare(trim(getFormData("SatGridSquare")));
 $Confirm=getFormData("Confirm");
 
 $callSignParser = new CallSignParser($SatCall);
 if (PARSE_GRID_SQUARE_FROM_CALLSIGN && $SatGridSquare == "" && $callSignParser -> baseCallSign() != "") {
     $SatCall = $callSignParser -> baseCallSign();
     $SatGridSquare = $callSignParser -> extractGridSquare();
 }
 
 $DisplayGridSquare = $SatGridSquare != "" ? $SatGridSquare : "* None entered *";

 // Error if sat name and report are empty
 if ($SatName == "" || $SatReport == "") {
   echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">You appear to be missing either the Satellite Name or Status Report.</div>";
	echo "<br><br><center><a href=\"index.php\">Go back</a></center>";
	exit;
 }

 // Error if callsign is missing
 if ($SatCall == "") {
   echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">You must enter a callsign.</div>";
   exit;
 }
 
 // Error if callsign doesn't look like a callsign
 if (!callSignIsValid($SatCall)) {
     echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">The callsign you entered does not appear to be valid</div>";
     echo "<br><br><center><a href=\"index.php\">Go back</a></center>";
     exit;
 }
 
 // Error if time/date submitted is in the future (Assumed to be UTC, as that is specified on the page)
 $SubmittedDateTime = $SatYear . "-" . $SatMonth . "-" . $SatDay . "T" . $SatHour . ":00:00+0000";
 $CurrentDateTime = gmdate(DATE_ISO8601);
 if ($SubmittedDateTime > $CurrentDateTime) {
    echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">The time heard you entered does not appear to be valid</div>";
    echo "<br><br><center><a href=\"index.php\">Go back</a></center>";
    exit;
 }

 // Error if grid square doesn't look like a grid square
 if ($SatGridSquare != "" && !gridSquareIsValid($SatGridSquare)) {
     echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">The grid square you entered does not appear to be valid</div>";
     echo "<br><br><center><a href=\"index.php\">Go back</a></center>";
     exit;
 }

# An attempt to prevent SQL injection attack JBF 02 APR 2017
 if(preg_match("/[\\\\\"~`=<>|'_+.,!@#\$%^&\*\(\)\{\}\[\]]/",$SatCall)){
    echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">Only special characters - and / allowed in callsign.</div>";
    exit;
}

 $SatCall = substr($SatCall,0,14);

 // Check to see if the satellite name matches
 if($SatName != "") {
   $conn = new mysqli($mysqlHost,$mysqlUsername,$mysqlPassword,$mysqlDatabase);
   $sql = "SELECT html_element_name FROM satellite_name WHERE html_element_name = '".$SatName."'";

   $result = $conn->query($sql);

   $rowcount=mysqli_num_rows($result);

   if ($rowcount != 1) {
     echo "<br><br><div style=\" font-family: Helvetica,Arial,sans-serif;padding: 10px; font-size:20px; width: 50%; margin: 0 auto; color: #a94442;background-color: #f2dede;border-color: #ebccd1;\">Satellite Name does not match.</div>";
     exit;
   }

   mysqli_close($conn);
 }
 // INSERT Status Report


 $db = mysqli_connect($mysqlHost, $mysqlUsername,$mysqlPassword);
 mysqli_select_db($db, $mysqlDatabase);


 if($Confirm == "yes")
   {
     if($SatReport!="")
       {
         $sql = sprintf("select id from satellite where name='%s' AND longname='%s' AND day='%s-%s-%s' AND hour='%s' AND period='%s' AND callsign='%s'",$SatName, $SatName, $SatYear, $SatMonth, $SatDay, $SatHour, $SatPeriod, $SatCall, $SatReport);

         $result = mysqli_query($db, $sql);
         while ($value = mysqli_fetch_array($result))
	   {
	     echo "<center><br>It appears that you have already made a report for this satellite for the selected time period.";
             echo "<br>This report will replace the previous one.<center>";

             $sql = sprintf("delete from satellite where id='%s'",$value[0]);
             mysqli_query($db, $sql);
           }

	 $sql = sprintf("INSERT INTO satellite VALUES ('%s','%s',NULL, NULL, '%s-%s-%s','%s','%s','%s','%s',NULL, '%s')",$SatName, $SatName, $SatYear, $SatMonth, $SatDay, $SatHour, $SatPeriod, $SatCall, $SatReport, $SatGridSquare);
	 $result = mysqli_query($db, $sql);

	 setcookie("amsatCallsign", $SatCall);
	 echo "<br><br><center>Thank you for your submission</center>" ;
   ?>

   <br>
   <center>
   <div class="paypal_button">
   <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input name="cmd" type="hidden" value="_s-xclick"> <input name="hosted_button_id" type="hidden" value="8KAAKMU2TXUQ4"> <span style="font-size:24px;">Support AMSAT</span> <br><br><input alt="PayPal - The safer, easier way to pay online!" border="0" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" type="image">&nbsp;</form>
   </div>
   </center>

   <?php
	 echo "<br><center><a href=index.php>Back to main page</a></center>";
       }
     else
       {
	 echo "<br><center><font color=red>Please return to the main page and select a value for \"Status Report\"</font><br><a href=index.php>main page</a></center>";
       }
   }
 else
   {
     echo "<center><b>You Entered:</b><br>";
     $DayOfWeek = date( "l" ,strtotime( sprintf("%s-%s-%s", $SatYear,$SatMonth,$SatDay)));
     printf("<table border=0><tr><td><b>Satellite</b></td><td>%s</td></tr><tr><td><b>Date</b></td><td>%s, %s-%s-%s</td></tr><tr><td><b>Time</b></td><td>%s",$SatName,$DayOfWeek,$SatMonth,$SatDay,$SatYear,$SatHour);
     if ($SatPeriod == 0)
       {
	 echo ":00-:15";
       }
     else if ($SatPeriod == 1)
       {
	 echo ":16-:30";
       }
     else if ($SatPeriod == 2)
       {
	 echo ":31-:45";
       }
     else if ($SatPeriod == 3)
       {
	 echo ":46-:59";
       }
     else
       {
	 echo "<font color=red>BAD TIME</font>";
       }

     printf("</td></tr><tr><td><b>Callsign</b></td><td>%s</td></tr><tr><td><b>Grid Square</b></td><td>%s</td></tr><tr><td><b>Report</b></td><td>",$SatCall, $DisplayGridSquare);

     if($SatReport == "Heard")
       {
	 echo "Transponder/Repeater active";
       }
     else
       {
	 echo $SatReport;
       }
     echo "</td></tr></table>";
     echo "<br><b>Is this correct?</b>";
     $rawSubmitUrl = sprintf("%s?SatSubmit=yes&Confirm=yes&SatName=%s&SatYear=%s&SatMonth=%s&SatDay=%s&SatHour=%s&SatPeriod=%s&SatCall=%s&SatReport=%s&SatGridSquare=%s",
         $_SERVER['PHP_SELF'],$SatName,$SatYear,$SatMonth,$SatDay,$SatHour,$SatPeriod,strtoupper($SatCall),$SatReport, $SatGridSquare);
     $submitUrl = str_replace(" ", "+", $rawSubmitUrl);
     printf("<br><br><a href=%s>Yes</a>&nbsp &nbsp<a href=index.php>No</a>", $submitUrl);
     echo "<br><br><center>Please note that the data on this page is only as good as the data that is entered by you.  Before posting a report make absolutely sure that your data is correct.</center>";
   }



}
?>

</body>
</html>

<?php

// Common functions

function getFormData($var, $default = null) {
    return ($val = getPost($var)) !== null
       ? $val : getGet($var, $default);
}

function getGet($var, $default = null) {
    return (array_key_exists($var, $_GET))
        ? dispelMagicQuotes($_GET[$var])
        : $default;
}

function getPost($var, $default = null) {
    return (array_key_exists($var, $_POST))
        ? dispelMagicQuotes($_POST[$var])
        : $default;
}

function dispelMagicQuotes(&$var) {
    static $magic_quotes;
    if (!isset($magic_quotes)) {
        $magic_quotes = false;
    }
    if ($magic_quotes) {
        if (!is_array($var)) {
            $var = stripslashes($var);
        } else {
            array_walk($var, 'dispelMagicQuotes');
        }
    }
    return str_replace("'", "\\'", $var);
}

/**
 *
 * determines if a string is formatted like an amateur radio or SWL call sign
 * @param string $callSignComponent the call sign component to be vaildated
 * @return boolean true if $callsign conforms to known formatting for worldwide amateur
 * radio and SWL call signs; false otherwise
 */
function callSignComponentIsValid($callSignComponent) {
    if (strlen($callSignComponent) == 0) {
        return false;
    }
    
    $isAlNum = preg_match("/^[a-zA-Z0-9]+$/", $callSignComponent);
    $hasDigit = preg_match("/[0-9]/", $callSignComponent);
    $hasLetter = preg_match("/[a-zA-Z]/", $callSignComponent);
    $validSeparatorPosition = !preg_match("/^\D\D\D/", $callSignComponent) || preg_match("/^onl/i", $callSignComponent);
    $usWithTwoDigits = preg_match("/^[kwKW]\D*\d\D*\d/", $callSignComponent)
    || preg_match("/^[nN]\d\D*\d/", $callSignComponent)
    || preg_match("/^[nN][a-km-zA-KM-Z]\D*\d\D*\d/", $callSignComponent);
    $pmWithMoreThanOneDigit = preg_match("/^pm\d\d+/i", $callSignComponent);
    $callTooLong = strlen($callSignComponent) > 10
    || (strlen($callSignComponent) > 6 &&
        !(
            preg_match("/^i\d{5}[a-zA-Z][a-zA-Z]$/", $callSignComponent) // i12345AB is an Italian SWL callsign
            || preg_match("/^nl\d{5}$/i", $callSignComponent) // NL12345 is Dutch SWL call
            || preg_match("/^pa\d{5}$/i", $callSignComponent) // PA12345 is a SWL call
            || preg_match("/^onl\d{4,5}$/i", $callSignComponent) // ONL1234 or ONL12345is a SWL call
            || preg_match("/^oe\d{4,8}$/i", $callSignComponent) // OE60200755 is Austrian SWL Call
            || preg_match("/^vk\d[f][a-zA-Z]{3}$/i", $callSignComponent) // Australian "Foundation" licenses
            )
        );
    $callTooShort = strlen($callSignComponent) < 4;
    
    return $isAlNum && $hasDigit && $hasLetter && $validSeparatorPosition
    && !$usWithTwoDigits && !$pmWithMoreThanOneDigit
    && !$callTooLong && !$callTooShort;
    
}

/**
 * determines if a string is formatted like an amateur radio or SWL call sign, allowing for a multi-component call sign
 * @author Ed Little KN6DBC, based on Perl code from Paul Williamson KB5MU
 * @param string $callSign the call sign to be validated
 * @return boolean true if any component of $callsign (delimited by "/" or "-") conforms to known
 * formatting for worldwide amateur radio and SWL call signs; false otherwise
 */
function callSignIsValid($callSign) {
    if (strlen($callSign) == 0) {
        return false;
    }
    
    $components = preg_split("/[-\/]/", $callSign);
    
    $result = false;
    
    for ($i = 0, $len = sizeOf($components); $i < $len; $i++) {
        if (callSignComponentIsValid($components[$i])) {
            $result = true;
            break;
        }
    }
    
    return $result;
}

/**
 * determines if a grid square conforms to either the field-square convention
 * or the field-square-subsquare convention
 * @param string $gridSquare
 * @return number 1 if $gridSquare confoms to one of the conventions; 0 if not; false if an error occurrs
 */
function gridSquareIsValid($gridSquare) {
    return preg_match("/^[A-Ra-r]{2}[0-9]{2}([a-xA-X]{2})?$/", $gridSquare);
}

/**
 * generates a string following the conventions for naming Maidenhead grid 
 * squares: capitalized field, lowercase sub-square
 * @param string $gridSquare the grid square
 * @return string a grid square generated by converting the characters in 
 * $gridSquare to conventional capitalization
 */
function standardizedGridSquare($gridSquare) {
    $result = "";
    
    for ($i = 0, $len = strlen($gridSquare); $i < $len; $i++) {
        $nextChar = $gridSquare[$i];
        if ($i < 2) { // field
            $nextChar = strtoupper($nextChar);
        } else if ($i >= 4) { // sub-square
            $nextChar = strtolower($nextChar);
        }
        $result = $result . $nextChar;
    }
    
    return $result;
}

/**
 * class for extracting a grid square from a call sign
 * @author Edward Little
 *
 */
class CallSignParser {
    private function delimitersRegex() {
        return "/[-\/]/";
    }
    
    public function __construct($callSign) {
        $this -> callSign = $callSign;
    }
    
    /**
     * gets the grid square from the call sign
     * @return string if the last slash- or hyphen-delimited component of an extended 
     * call sign looks like a grid square, the last component; otherwise an empty string
     */
    public function extractGridSquare() {
        $result = "";
        
        $components = preg_split($this -> delimitersRegex(), $this -> callSign);
        $len = sizeOf($components);
        if ($len > 1) {
            $lastComponent = $components[$len - 1];
            if (gridSquareIsValid($lastComponent)) {
                $result = standardizedGridSquare($lastComponent);
            }
        }
        
        return $result;
    }
    
    /**
     * gets the call sign with the grid square component removed
     * @return string the call sign with the last slash- or hyphen-delimited component 
     * removed if the last component looks like a grid square; the whole call sign 
     * if not; or a blank string if the call sign can not be parsed (e. g. if the entire
     * call sign string is made up of slashes and hyphens)
     */
    public function baseCallSign() {
        $result = $this -> callSign;
        
        $gridSquare = $this -> extractGridSquare();
        if ($gridSquare != "" && $this -> callSign != "") {
            $initialLength = strlen($this -> callSign) - strlen($gridSquare);
            $baseWithLastDelimiter = substr($this -> callSign, 0, $initialLength);
            $lastCallsignCharIndex = null;
            for ($i = $initialLength - 1; $i >= 0; $i--) {
                if (!preg_match($this -> delimitersRegex(), $baseWithLastDelimiter[$i])) {
                    $lastCallsignCharIndex = $i;
                    break;
                }
            }
            $result = $lastCallsignCharIndex != null
            ? substr($this -> callSign, 0, $lastCallsignCharIndex + 1)
                : "";
        }
        
        return callSignIsValid($result) ? $result : "";
    }    
}

?>
