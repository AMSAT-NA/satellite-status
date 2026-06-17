<?php
// Returning false tells the PHP built-in server to handle the request directly.
// This gives proper 404 responses for missing files, rather than falling back
// to the directory's index.php.
return false;
