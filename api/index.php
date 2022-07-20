<?php

echo("Send a query like :  amsat.org/status/api/v1/sat_info.php?name=AO-91&hours=24 and you will get the last 24 hours of reports for AO-91 in JSON format.   The hours parameter is optional, if you omit it you will get the last 96 hours of reports.  The name of the satellite must match the string shown on amsat.org/status ,  i.e AO-91 works, but AO-92 does not ... use AO-92_L/v or AO-92_U/v  instead.");
echo("This API is not stable yet ... we are still working on the time, and it seems a query for the list of available satellites is in order.   For the moment, all reports show half past the hour that they were in.");
exit();
