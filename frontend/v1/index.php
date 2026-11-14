<?php
date_default_timezone_set('UTC'); ?>
<? ob_start("ob_gzhandler"); ?>
<?php // Include files
include("config.php");

// Site banner (reusable, file-toggled -- see content/banner.md.example):
// direct file check here rather than a preflight request to banner.php,
// so an inactive banner costs nothing beyond a filesystem stat and we
// never emit an empty <iframe> box.
$bannerPath = __DIR__ . '/content/banner.md';
$hasBanner = is_file($bannerPath) && trim((string) file_get_contents($bannerPath)) !== '';
?>
<html>
<head>
<title>AMSAT OSCAR Satellite Status</title>

<style media="screen">
  body {
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
    font-size: 14px;
    line-height: 1.42857143;
    color: #333;
  }
</style>

<style type="text/css">
<!--

.tipClass { font: 14px Arial, Helvetica; color: white }

-->
</style>

<style media="screen">
  .reportsFromList {
    max-height: 500px;
    overflow-y: auto;
    width: 100%;
    text-align: center;
  }
</style>

</head>

<body link="black" alink="black" vlink="black">
<center><font size=5><b>AMSAT Live OSCAR Satellite Status Page</b></font></center>
<br>

<?php if ($hasBanner): ?>
<center>
<iframe id="site-banner" src="banner.php" title="Site announcement"
        style="border:0; width:100%; max-width:75%; height:0;" scrolling="no"></iframe>
</center>
<script>
  // Only trust postMessage from this specific iframe -- checking
  // event.source (not just event.origin) is the robust way to do that,
  // since origin alone can't distinguish this iframe from any other
  // same-origin frame/window that might also post a message.
  (function () {
    var bannerFrame = document.getElementById('site-banner');
    window.addEventListener('message', function (event) {
      if (!bannerFrame || event.source !== bannerFrame.contentWindow) {
        return;
      }
      if (event.data && event.data.source === 'site-banner' && typeof event.data.height === 'number') {
        bannerFrame.style.height = event.data.height + 'px';
      }
    });
  })();
</script>
<br>
<?php endif; ?>

<center>
<table width="75%">
<tr><td>
This web page was created to give a single global reference point for all
users in the Amateur Satellite Service to show the most up-to-date status
of all satellites as reported by users around the world.  Please help 
others and keep it current every time you access a bird.
</td></tr>
</table>
</center>

<br><center>
<table border=0><tr><td style="background-color: #648fff; color: white;"><b>Sat/Mode Active</b></td><td style="background-color: #ffb000; color: black;"><b>TLM/Beacon only</b></td><td style="background-color: #dc267f; color: black;"><b>Not Heard</b></td><td style="background-color: #fe6100; color: black;"><b>Conflicting reports</b></td></tr></table>
</center>

<!--- BEGIN MAIN SAT CHART -->

<?php

$nDays = 6;

// Issue #67: the page reads everything through the API instead of
// MariaDB. The grid, its tooltips and "Reports From" are still computed
// here from raw reports, exactly as the SQL below used to do.
require_once __DIR__ . '/lib/status_api.php';

// Issue #46: the window is today plus the previous $nDays - 1 UTC days,
// from 00:00 UTC, up to (not including) 00:00 UTC tomorrow. The bounds are
// whole days, so no 15-minute dedup period is split.
$nTodayStart = intdiv(time(), 86400) * 86400;
$sWindowStart = gmdate('Y-m-d\TH:i:s\Z', $nTodayStart - ($nDays - 1) * 86400);
$nWindowEnd = $nTodayStart + 86400;

$aWindowReports = status_api_reports_since($apiInternalUrl, $sWindowStart);
$aCatalog = status_api_catalog($apiInternalUrl);
$bStatusDataAvailable = $aWindowReports !== null && $aCatalog !== null;

// Every report in the window, superseded ones included (PR #30, Issue
// #50): superseded_by is null for the report that counts, so "counted"
// is the API's superseded_by === null. day/hour/twohour/col are what the
// old queries derived with DATE()/HOUR() and the grid-column expression;
// col 0 is today's 22:00-23:59 cell, counting back 2 hours per column.
$aTipRows = [];
foreach ($aWindowReports ?? [] as $aReport) {
    $nObserved = strtotime((string) $aReport['observation_time']);
    if ($nObserved === false || $nObserved >= $nWindowEnd) {
        continue; // future-dated legacy rows were never on the grid
    }
    $nHour = (int) gmdate('G', $nObserved);
    $aTipRows[] = [
        'name' => (string) $aReport['name'],
        'report' => (string) $aReport['report'],
        'id' => (int) $aReport['id'],
        'callsign' => (string) $aReport['callsign'],
        'day' => gmdate('Y-m-d', $nObserved),
        'twohour' => intdiv($nHour, 2),
        'grid_square' => (string) ($aReport['grid_square'] ?? ''),
        'observed' => $nObserved,
        'counted' => $aReport['superseded_by'] === null,
        'col' => intdiv(23 - $nHour, 2) + intdiv($nTodayStart - intdiv($nObserved, 86400) * 86400, 86400) * 12,
    ];
}

// The grid only counts each callsign's latest submission per period.
$aGridRows = array_values(array_filter($aTipRows, static fn (array $r): bool => $r['counted']));

// Same orders the SQL used: tooltips by name, cell (newest first), id
// descending; grid by name, column, id descending. Name order follows
// the tables' collation (status_name_compare).
usort($aTipRows, static function (array $a, array $b): int {
    return status_name_compare($a['name'], $b['name'])
        ?: strcmp($b['day'], $a['day'])
        ?: $b['twohour'] <=> $a['twohour']
        ?: $b['id'] <=> $a['id'];
});
usort($aGridRows, static function (array $a, array $b): int {
    return status_name_compare($a['name'], $b['name'])
        ?: $a['col'] <=> $b['col']
        ?: $b['id'] <=> $a['id'];
});

$aWebsites = [];
foreach ($aCatalog ?? [] as $aSatellite) {
    $aWebsites[(string) $aSatellite['name']] = (string) ($aSatellite['website'] ?? '');
}

?>
<!--       TIPSTER v3.0        -->
<!--     by Angus Turnbull      -->
<!--  http://www.twinhelix.com  -->
<!--   Visit for more scripts!  -->

<script language="JavaScript"><!--

// *** COMMON CROSS-BROWSER COMPATIBILITY CODE ***

var isDOM=document.getElementById?1:0;
var isIE=document.all?1:0;
var isNS4=navigator.appName=='Netscape'&&!isDOM?1:0;
var isOp=window.opera?1:0;
var isWin=navigator.platform.indexOf('Win')!=-1?1:0;
var isDyn=isDOM||isIE||isNS4;


function getRef(id, par)
{
 par=!par?document:(par.navigator?par.document:par);
 return isIE ? par.all[id] :
  (isDOM ? (par.getElementById?par:par.ownerDocument).getElementById(id) :
  (isNS4 ? par.layers[id] : null));
}

function getSty(id, par)
{
 var r=getRef(id, par);
 return r?(isNS4?r:r.style):null;
}


if (!window.LayerObj) var LayerObj = new Function('id', 'par',
 'this.ref=getRef(id, par); this.sty=getSty(id, par); return this');
function getLyr(id, par) { return new LayerObj(id, par) }

function LyrFn(fn, fc)
{
 LayerObj.prototype[fn] = new Function('var a=arguments,p=a[0],px=isNS4||isOp?0:"px"; ' +
  'with (this) { '+fc+' }');
}
LyrFn('x','if (!isNaN(p)) sty.left=p+px; else return parseInt(sty.left)');
LyrFn('y','if (!isNaN(p)) sty.top=p+px; else return parseInt(sty.top)');
LyrFn('w','if (p) (isNS4?sty.clip:sty).width=p+px; ' +
 'else return (isNS4?ref.document.width:ref.offsetWidth)');
LyrFn('h','if (p) (isNS4?sty.clip:sty).height=p+px; ' +
 'else return (isNS4?ref.document.height:ref.offsetHeight)');
LyrFn('vis','sty.visibility=p');
LyrFn('write','if (isNS4) with (ref.document){write(p);close()} else ref.innerHTML=p');
LyrFn('alpha','var f=ref.filters,d=(p==null); if (f) {' +
 'if (!d&&sty.filter.indexOf("alpha")==-1) sty.filter+=" alpha(opacity="+p+")"; ' +
 'else if (f.length&&f.alpha) with(f.alpha){if(d)enabled=false;else{opacity=p;enabled=true}} }' +
 'else if (isDOM) sty.MozOpacity=d?"":p+"%"');


var CSSmode=document.compatMode;
CSSmode=(CSSmode&&CSSmode.indexOf('CSS')!=-1)||isDOM&&!isIE||isOp?1:0;

if (!window.page) var page = { win: window, minW: 0, minH: 0, MS: isIE&&!isOp,
 db: CSSmode?'documentElement':'body' }

page.winW=function()
 { with (this) return Math.max(minW, MS?win.document[db].clientWidth:win.innerWidth) }
page.winH=function()
 { with (this) return Math.max(minH, MS?win.document[db].clientHeight:win.innerHeight) }

page.scrollY=function()
 { with (this) return MS?win.document[db].scrollTop:win.pageYOffset }
page.scrollX=function()
 { with (this) return MS?win.document[db].scrollLeft:win.pageXOffset }

// *** TIP FUNCTIONS AND OBJECT ***

function tipTrack(evt, always) { with (this)
{
 // Reference the correct event object.
 evt=evt?evt:window.event;

 // Figure out the mouse co-ordinates and call the position function.
 // Also set sX and sY as the scroll position of the document.
 sX = page.scrollX();
 sY = page.scrollY();
 mX = isNS4 ? evt.pageX : sX + evt.clientX;
 mY = isNS4 ? evt.pageY : sY + evt.clientY;

 // If we've set tip tracking, call the position function.
 if (tipStick == 1) position();
}}

function tipPosition(forcePos) { with (this)
{
 // Can't position a tip if there isn't one available...
 if (!actTip) return;

 // Pull the window sizes from the page object.
 // In NS we size down the window a little as it includes scrollbars.
 var wW = page.winW()-(isIE?0:15), wH = page.winH()-(isIE?0:15);

 // Pull the compulsory information out of the tip array.
 var t=tips[actTip], tipX=eval(t[0]), tipY=eval(t[1]), tipW=div.w(), tipH=div.h(), adjY = 1;

 // Add mouse position onto relatively positioned tips.
 if (typeof(t[0])=='number') tipX += mX;
 if (typeof(t[1])=='number') tipY += mY;

 // Check the tip is not within 5px of the screen boundaries.
 if (tipX + tipW + 5 > sX + wW) { tipX = sX + wW - tipW - 5; adjY = 2 }
 if (tipY + tipH + 5 > sY + wH) tipY = sY + wH - (adjY*tipH) - 5;
 if (tipX < sX+ 5) tipX = sX + 5;
 if (tipY < sY + 5) tipY = sY + 5;

 // If the tip is currently invisible, show at the calculated position.
 // Also do this if we're passed the 'forcePos' parameter.
 if ((!showTip && (doFades ? !alpha : true)) || forcePos)
 {
  xPos = tipX;
  yPos = tipY;
 }

 // Otherwise move the tip towards the calculated position by the stickiness factor.
 // Low stickinesses will result in slower catchup times.
 xPos += (tipX - xPos) * tipStick;
 yPos += (tipY - yPos) * tipStick;

 div.x(xPos);
 div.y(yPos);
}}

function tipShow(tipN) { with (this)
{
 if (!isDyn) return;

 // If this tip is nested, call the 'show' function of its parent too.
 if (tips[tipN].parentObj) tips[tipN].parentObj.show(tips[tipN].parentTip);

 // My layer object we use.
 if (!div) div = getLyr(myName + 'Layer');

 // IE4 requires a small width set otherwise tip divs expand to full body size.
 if (isDOM) div.sty.width = 'auto';

 // If we're mousing over a different or new tip...
 if (actTip != tipN)
 {
  // Remember this tip number as active, for the other functions.
  actTip = tipN;

  // Set tip's onmouseover and onmouseout handlers for static tips.
  if (tipStick == 0)
  {
   if (isNS4) div.ref.captureEvents(Event.MOUSEOVER | Event.MOUSEOUT);
   div.ref.onmouseover = new Function('evt', myName + '.show("' + tipN + '"); ' +
    'if (isNS4) return this.routeEvent(evt)');
   div.ref.onmouseout = new Function('evt', myName + '.hide(); ' +
   'if (isNS4) return this.routeEvent(evt)');
  }

  // Place it somewhere onscreen - pass true to force a complete reposition.
  position(true);

  // Go through and replace %0% with the array's 0 index, %1% with tips[tipN][1] etc...
  var str = template;
  for (var i=0; i<tips[tipN].length; i++) str = str.replace('%'+i+'%', tips[tipN][i]);
  // Write the proper content... the last <br> strangely helps IE5/Mac...?
  div.write(str + ((document.all && !isWin) ? '<small><br></small>' : ''));
 }

 // For non-integer stickiness values, we need to use setInterval to animate the tip,
 // if it's 0 or 1 we can just use onmousemove to position it.
 clearInterval(trackTimer);
 if (tipStick != parseInt(tipStick)) trackTimer = setInterval(myName+'.position()', 50);

 // Finally either fade in immediately or after 'showDelay' milliseconds.
 // NS4 must always delay by a small amount as sometimes hide events come before show events
 // from a previous mouseout (when two tip triggers overlap), because it's a weird browser.
 // So, this show call can cancel a (slightly later) hide.
 clearTimeout(fadeTimer);
 if (showDelay || isNS4)
  fadeTimer = setTimeout('with ('+myName+') { showTip = true; fade() }', showDelay + 10);
 else { showTip = true; fade() }
}}


function tipHide() { with (this)
{
 // We've got to be a DHTML-capable browser that has a tip currently active.
 if (!isDyn || !actTip) return;

 // If the mouse position is within the tip boundaries, we know NS4 is telling us stories
 // as often it makes hide events unaccompanied by overs or in a weird order.
 // Only applies to static tips that we want the user to mouseover...
 if (isNS4 && tipStick==0 && xPos<=mX && mX<=xPos+div.w() && yPos<=mY && mY<=yPos+div.h())
  return;

 // If this tip is nested, call the 'hide' function of its parent too.
 if (tips[actTip].parentObj) tips[actTip].parentObj.hide();

 // Fade out after a delay so another mouseover can cancel this fade.
 // This allows the user to mouseover a static tip before its hides.
 clearTimeout(fadeTimer);
 fadeTimer = setTimeout('with (' + myName + ') { showTip=false; fade() }', hideDelay);
}}


function tipFade() { with (this)
{
 // Clear to stop existing fades.
 clearTimeout(fadeTimer);

 // Show it and optionally increment alpha from minAlpha to maxAlpha or back again.
 if (showTip)
 {
  div.vis('visible');
  if (doFades)
  {
   alpha += fadeSpeed;
   if (alpha > maxAlpha) alpha = maxAlpha;
   div.alpha(alpha);
   // Call this function again shortly, fading tip in further.
   if (alpha < maxAlpha) fadeTimer = setTimeout(myName + '.fade()', 50);
  }
 }

 else
 {
  // Similar to before but counting down and hiding at the end.
  if (doFades && alpha > minAlpha)
  {
   alpha -= fadeSpeed;
   if (alpha < minAlpha) alpha = minAlpha;
   div.alpha(alpha);
   fadeTimer = setTimeout(myName + '.fade()', 50);
   return;
  }
  div.vis('hidden');
  // Clear the active tip flag so it is repositioned next time.
  actTip = '';
  // Stop any sticky-tip tracking if it's invisible.
  clearInterval(trackTimer);
 }
}}


function TipObj(myName)
{
 // Holds the properties the functions above use.
 this.myName = myName;
 this.tips = new Array();
 this.template = '';
 this.actTip = '';
 this.showTip = false;
 this.tipStick = 0;
 this.showDelay = 50;
 this.hideDelay = 50;
 this.xPos = this.yPos = this.sX = this.sY = this.mX = this.mY = 0;

 this.track = tipTrack;
 this.position = tipPosition;
 this.show = tipShow;
 this.hide = tipHide;
 this.fade = tipFade;

 this.div = null;
 this.trackTimer = this.fadeTimer = 0;
 this.alpha = 0;
 this.doFades = true;
 this.minAlpha = 0;
 this.maxAlpha = 100;
 this.fadeSpeed = 10;
}


<!-- mystuff now -->

var docTips = new TipObj('docTips');
with (docTips)
{

<?php

// Get all reports for tooltip generation: every report in the window
// (see $aTipRows above). Earlier submissions that a later one from the
// same callsign in the same 15-minute period superseded are still listed,
// greyed out and marked as corrected, but don't count toward the cell's
// colour. Rows with an empty callsign (legacy data only) are never
// superseded. Same rule as the API (LATEST_PER_CALLSIGN_SQL in
// api/v1/lib/repository.php), which now supplies superseded_by.

$sLastName = "";
$sLastDay = "";
$nLastTwoHour = 0;
$bStart = true;

foreach ($aTipRows as $aRow)
{
    $bSameName = $aRow["name"] == $sLastName;
    $bSameDay = $aRow["day"] == $sLastDay;
    $bSameTwoHour = $aRow["twohour"] == $nLastTwoHour;

    $bFirst = !($bSameName && $bSameDay && $bSameTwoHour);

    $sLastName = $aRow["name"];
    $sLastDay = $aRow["day"];
    $nLastTwoHour = $aRow["twohour"];


    if ($bFirst)
    {
        if (!$bStart)
        {
            echo("');\n");
        }
        echo("tips.a" . $aRow["id"] . " = new Array( 5, 5, 120, '");
    }
    else
    {
        echo("<br><br>");
    }

    $gridSquareElement = "";
    if ($aRow["grid_square"] != "") {
        $gridSquareElement = "<br>" . $aRow["grid_square"];
    }

    // Issue #24: display the actual observation_time timestamp instead of a
    // reconstructed quarter-hour period range -- a precision improvement,
    // since observation_time is minute-accurate (period was only a 15-minute
    // bucket).
    $observationTimeDisplay = gmdate("Y-m-d H:i", $aRow["observed"]);
    $tipEntry = $aRow["report"] . "<br>" . $aRow["callsign"] . $gridSquareElement . "<br>" . $observationTimeDisplay . " UTC";
    if (!$aRow["counted"]) {
        // Superseded by a later submission from the same callsign in this
        // cell -- shown for transparency, not counted. No quotes in this
        // markup: the tooltip body is a single-quoted JS string.
        $tipEntry = "<font color=#999999><s>" . $tipEntry . "</s><br><i>corrected by a later report</i></font>";
    }
    echo($tipEntry);

    $bStart = false;
}

if (!$bStart)
{
    echo("');\n");
}

?>
template = '<table bgcolor="#003366" cellpadding="1" cellspacing="0" width="%2%" border="0">' +
  '<tr><td><table bgcolor="#6699CC" cellpadding="3" cellspacing="0" width="100%" border="0">' +
  '<tr><td class="tipClass">%3%</td></tr></table></td></tr></table>';

}

if (isNS4) document.captureEvents(Event.MOUSEMOVE);
document.onmousemove = function(evt)
{

 // Add or remove all your tip objects from here!
 docTips.track(evt);

 if (isNS4) return document.routeEvent(evt);
}


// A small function that refreshes NS4 on horizontal resize.
var nsWinW = window.innerWidth, nsWinH = window.innerHeight;
function ns4BugCheck()
{
 if (isNS4 && (nsWinW!=innerWidth || nsWinH!=innerHeight)) location.reload()
}

window.onresize = function()
{
 ns4BugCheck();
}


//--></script>



<div id="docTipsLayer" style="position: absolute; z-index: 1000; visibility: hidden;
 left: 0px; top: 0px; width: 10px">&nbsp;</div>

<center>
<table border=0 cellspacing=1 cellpadding=0><tr>
<td width=50 align="right"><b>Name</b></td>

<?php

// Make the table

for ($nDay = 0; $nDay < $nDays; $nDay++)
{
    if ($nDay / 2 == floor($nDay / 2))
    {
        $sBackColor = "black";
        $sTextColor = "white";
    }
    else
    {
        $sBackColor = "white";
        $sTextColor = "black";
    }

    echo("<td colspan=12 width=108 align=\"center\" bgcolor=\"" . $sBackColor . "\">");
    echo("<font color=\"" . $sTextColor . "\">" . date("M j", time() - (86400 * $nDay)) . "</font></td>\n");
}

echo("</tr>");


// Only each callsign's latest submission per cell is counted (see the
// tooltip rows above). The cell's tooltip key is the highest id in the
// cell, which is always a counted row, so it still matches the tooltip.
$nGridRow = 0;

$sLastName = "";
$sLastDay = "";
$nLastColumn = 0;
$nCurColumn = 0;

// Fetch first row
$aRow = $aGridRows[$nGridRow++] ?? null;

if ($aRow)
{
    $bSameName = $aRow["name"] == $sLastName;
    $sLastName = $aRow["name"];
    $bSameColumn = $aRow["col"] == $nLastColumn;
    $nLastColumn = $aRow["col"];
}

// Print rows in satellite status table
while ($aRow)
{
    if (!$bSameName)
    {
        $satPage = "";

        // We're on a new row, so reset current column number
        $nCurColumn = 0;

        $satPage = $aWebsites[$aRow["name"]] ?? "";

        // Only add link if we have a site
        if ($satPage != "")
        {
            echo("<tr> <td align=\"right\"><a target=\"_new\" href=\"" . $satPage . "\">" . $aRow["name"] . "</a></td>\n");
        }
        else
        {
            echo("<tr> <td align=\"right\">" . $aRow["name"] . "</td>\n");
        }
    }

    // Put in dummy cells until we reach the one we need
    while ($nCurColumn < $aRow["col"])
    {
        $nCurColumn++;
        echo("<td width=9 bgcolor=\"C0C0C0\"> </td>");
    }

    $idNum = $aRow["id"];

    $nHeard = 0;
    $nNotHeard = 0;
    $nTelemetry = 0;
    $nCrew = 0;

    $majReport = 1;

    // Check status of current row
    switch ($aRow["report"])
    {
        case "Heard":
            $nHeard = 1;
            break;
        case "Not Heard":
            $nNotHeard = 1;
            break;
        case "Telemetry Only":
            $nTelemetry = 1;
            break;
        case "Crew Active":
            $nCrew = 1;
    }

    // Fetch next row
    $aRow = $aGridRows[$nGridRow++] ?? null;
    if ($aRow)
    {
        $bSameName = $aRow["name"] == $sLastName;
        $sLastName = $aRow["name"];
        $bSameColumn = $aRow["col"] == $nLastColumn;
        $nLastColumn = $aRow["col"];

        // As long as we keep finding data for the same cell, keep retrieving rows
        while ($bSameName && $bSameColumn)
        {
            if ($aRow["report"] == 'Heard')
            {
                $nHeard++;
            }
            else if ($aRow["report"] == 'Not Heard')
            {
                $nNotHeard++;
            }
            else if ($aRow["report"] == 'Telemetry Only')
            {
                $nTelemetry++;
            }
            else if ($aRow["report"] == 'Crew Active')
            {
                $nCrew++;
            }

            $aRow = $aGridRows[$nGridRow++] ?? null;

            if ($aRow)
            {
                $bSameName = $aRow["name"] == $sLastName;
                $sLastName = $aRow["name"];
                $bSameColumn = $aRow["col"] == $nLastColumn;
                $nLastColumn = $aRow["col"];
            }
            else
            {
                break;
            }
        }
    }

    // Determine the number of reports and color
    if ($nCrew > 0) // Most crew
    {
        $majReport = $nCrew + $nHeard;
        $color = "#785ef0";
    }
    else if ($nHeard > $nNotHeard && $nHeard > $nTelemetry) // Most heard
    {
        $majReport = $nHeard;
        $color = "#648fff";
    }
    else if ($nNotHeard > $nHeard && $nNotHeard > $nTelemetry) // Most not heard
    {
        $majReport = $nNotHeard;
        $color = "#dc267f";
    }
	else if ($nTelemetry > $nNotHeard && $nTelemetry > $nHeard) // Most telemetry
    {
        $majReport = $nTelemetry;
        $color = "#ffb000";
    }
    else if ($nTelemetry + $nNotHeard + $nHeard >= 1) // Conflicting
    {
        $majReport = '_';
        $color = "#fe6100";
    }
    else
    {
        $majReport = ' ';
        $color = "#C0C0C0";
    }

    // Finally print cell
    echo("<td width=9 bgcolor=\"" . $color . "\"><a href=javascript:void(0) style=\"text-decoration:none\" onMouseOver=docTips.show('a" .
        $idNum . "') onMouseOut=docTips.hide()>" . $majReport . "</a></td>\n");
    $nCurColumn++;

    if (!$aRow || !$bSameName)
    {
        // Put in dummy cells until we reach the end
        $nColumns = $nDays * 12;
        while ($nCurColumn < $nColumns)
        {
            $nCurColumn++;
            echo("<td width=9 bgcolor=\"C0C0C0\"> </td>");
        }
        echo("</tr>\n");
    }
}

echo("</table>");

if (!$bStatusDataAvailable)
{
    echo("<p><b>Status data is temporarily unavailable. Please try again in a few minutes.</b></p>");
}

?>
</center>

<!--- END MAIN SAT CHART -->


<br>
<center><b>Hover mouse over number for more data. Satellites do not appear if they have no data available.</b>
</center>

<br>
<table width="100%" border=0>
<tr>
<td width="20%" align="center" cellpadding=20 valign="top"><font color="4169E1" size=4><b>Reports From:</b></font>
<br>
<div class="reportsFromList">
<?php
// Each callsign with a report in the window, most recent first. Every
// report counts here, superseded ones included, as the old query did.
// Callsigns compare case-insensitively (as the column's collation did),
// and TEST and blank callsigns are left out.
$aLastReport = [];
foreach ($aTipRows as $aRow)
{
    $sCallsign = strtoupper(rtrim($aRow["callsign"]));
    if ($sCallsign === "" || $sCallsign === "TEST")
    {
        continue;
    }
    $aLastReport[$sCallsign] = max($aLastReport[$sCallsign] ?? 0, $aRow["observed"]);
}
arsort($aLastReport);
foreach (array_keys($aLastReport) as $sCallsign)
{
  printf("<br><b>%s</b>", htmlspecialchars((string) $sCallsign));
}

?>
</div>
</td>
<td width="80%" align="center" valign="top">
<br>

<center>
<table bgcolor=ffcccc>
<tr><td>
<b>To correct a report made in error:</b>
<br>Enter the same data as before, except with the correct status.
<br>Then click submit---the original entry will be corrected.
</td></tr>
</table>
</center>

<br>
<table width="60%" cellpadding=10 border=0>
<tr><td colspan=2 align="center">
<Font size=5>Submit Report</font>
</tr></td>

<tr>
<td><font size=4><b>Satellite</b></font></td>
<td>
<form method="post" action="submit.php">

<select name="SatName">
  <option value="">Select Satellite</option>

<?php
// Issue #67: from the API catalog, which is already ordered by display name.
if ($aCatalog === null) {
    echo "Satellite list unavailable";
} elseif ($aCatalog === []) {
    echo "0 results";
} else {
    foreach ($aCatalog as $aSatellite) {
        ?>
        <option value="<?php echo htmlspecialchars((string) $aSatellite['name']);?>"><?php echo htmlspecialchars((string) $aSatellite['display_name']);?></option>
        <?php
    }
}
?>

</select>
</td></tr>

<tr>
<td><font size=4><b>Status Report</b></font></td>
<td>
<input type=radio name=SatReport value="Heard" id="satelliteActiveRadio"><label for="satelliteActiveRadio">Sat/Mode Active</label>
<br><input type=radio name=SatReport value="Telemetry Only" id="telemetryOnlyRadio"><label for="telemetryOnlyRadio">TLM/Beacon Only</label>
<br><input type=radio name=SatReport value="Not Heard" id="notHeardRadio"><label for="notHeardRadio">Not Heard</label>
</td>
</tr>

<tr>
<td><font size=4><b>Date Heard</b></font></td>
<td>
<select name=SatMonth>
<?php printf("<option value=%s>%s", date("m"), date("M")); ?>
<option value="01">Jan
<option value="02">Feb
<option value="03">Mar
<option value="04">Apr
<option value="05">May
<option value="06">Jun
<option value="07">Jul
<option value="08">Aug
<option value="09">Sep
<option value="10">Oct
<option value="11">Nov
<option value="12">Dec
</select>
<select name=SatDay>
<?php printf("<option value=%s>%s", date("d"), date("d")); ?>
<option value="01">01
<option value="02">02
<option value="03">03
<option value="04">04
<option value="05">05
<option value="06">06
<option value="07">07
<option value="08">08
<option value="09">09
<option value="10">10
<option value="11">11
<option value="12">12
<option value="13">13
<option value="14">14
<option value="15">15
<option value="16">16
<option value="17">17
<option value="18">18
<option value="19">19
<option value="20">20
<option value="21">21
<option value="22">22
<option value="23">23
<option value="24">24
<option value="25">25
<option value="26">26
<option value="27">27
<option value="28">28
<option value="29">29
<option value="30">30
<option value="31">31
</select>


<select name="SatYear">
<option value="<?php echo date("Y");?>" selected><?php echo date("Y");?></option>
<option value="<?php echo date("Y",strtotime("-1 year")); ?>"><?php echo date("Y",strtotime("-1 year")); ?></option>
</select>
</td></tr>
<tr>
<td><font size=4><b>Time Heard (UTC)</b></font></td>
<td>
<!-- Plain text, not type="time": browsers render type="time" in the
     device locale (AM/PM on en-US), which conflicts with a UTC field. -->
<input type="text" name="SatTime" value="<?php echo date("H:i"); ?>"
       pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" inputmode="numeric"
       maxlength="5" size="5" placeholder="HH:MM" required
       title="24-hour UTC time, HH:MM (e.g. 14:30 or 9:05)"> UTC, 24-hour (HH:MM)
</td></tr>

<tr>
<td><font size=4><b>Your Callsign</b></font></td>
<!--- JBF 8 JUL 2017 removed cookie filling in callsign -->
<td><input type="text" name="SatCall" value=""></td>
</tr>

<tr>
	<td>
		<font size="4">
			<b>
				Your Grid Square
			</b>
		</font>
	</td>
	<td>
		<input type="text" name="SatGridSquare" title="Grid Square is optional" value=""/>
	</td>
</tr>

<tr>
<td> </td>
<td>
<input type="Submit" name="SatSubmit" value="Submit Data">
</form>
</td></tr>
</table>

<div class="contact_support">
<span style="font-size:18px;">Questions? E-mail support at: webmaster@amsat.org</span>
</div>

<!--- main layout table-->
</td>
</tr>
</table>
<hr>
<br>
<center>
&copy; Radio Amateur Satellite Corporation (AMSAT)
<div class="paypal_button" style="margin-top:10px;">
<a href="https://www.amsat.org/donations/amsat-general-fund-donations/" style="display:inline-block;padding:6px 12px;background-color:#648fff;color:#ffffff;text-decoration:none;border-radius:4px;font-size:16px;">Support AMSAT</a>
</div>
<p style="font-size:12px;color:#888;"><?php if ($appCommitSha !== null && $appDeployedAt !== null): ?>Last updated <?php echo htmlspecialchars(date('m/d/Y H:i', strtotime($appDeployedAt))); ?> UTC (commit <?php echo htmlspecialchars(substr($appCommitSha, 0, 7)); ?>) &middot; <?php endif; ?><a href="https://github.com/AMSAT-NA/satellite-status/blob/main/ACKNOWLEDGEMENTS.md" target="_blank" rel="noopener" style="color:#888;">Acknowledgments</a> &middot; <a href="/api/v1/overview.php" style="color:#888;">API</a> &middot; <a href="https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG" target="_blank" rel="noopener" style="color:#888;">Changelog</a></p>
<br>
</body>
</html>
