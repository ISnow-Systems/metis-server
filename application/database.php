<?php
function getConnection(): mysqli
{
	$dbCredentials = json_decode(file_get_contents(dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . ".cred.json"), TRUE)["database"];
	return new mysqli($dbCredentials["hostname"], $dbCredentials["username"], $dbCredentials["password"], $dbCredentials["database"]);
}
