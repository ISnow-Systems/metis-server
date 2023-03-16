<?php
require_once dirname(__FILE__, 4) . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "database.php";
const BASE_ADDRESS = "https://iss.ndl.go.jp/api/opensearch?isbn=";
if (empty($_GET["isbn"])) {
	http_response_code(400);
	header("Content-Type: application/json");
	echo "{}";
	return;
} else {
	$connection = getConnection();
	$preparedQuery = $connection->prepare("SELECT title, title_kana, volume, authors, publishers FROM books WHERE isbn=? LIMIT 1;");
	$preparedQuery->bind_param("s", $_GET["isbn"]);
	$title = "";
	$title_kana = "";
	$volume = "";
	$authors = "";
	$publishers = "";
	$preparedQuery->bind_result($title, $title_kana, $volume, $authors, $publishers);
	if (!$preparedQuery->execute()) {
		http_response_code(500);
		header("Content-Type: application/json");
		echo "{\"error\": \"SQL Query Failed\"}";
		return;
	}
	if ($preparedQuery->num_rows != 1 && empty($title)) {
		$preparedQuery->close();
		$bookInfoRaw = file_get_contents(BASE_ADDRESS . $_GET["isbn"]);
		$bookInfoXml = simplexml_load_string($bookInfoRaw);
		if ($bookInfoXml === false) {
			http_response_code(500);
			header("Content-Type: application/json");
			echo "{\"error\": \"XML Parse failed\"}";
			return;
		}
		if (intval($bookInfoXml->channel->children("openSearch", true)->totalResults, 10) == 0) {
			http_response_code(404);
			header("Content-Type: application/json");
			echo "{}";
			return;
		}
		$title = $bookInfoXml->channel->item[0]->title;
		$title_kana = $bookInfoXml->channel->item[0]->children("dcndl", true)->titleTranscription;
		$volume = $bookInfoXml->channel->item[0]->children("dcndl", true)->volume;
		$iVolume = intval($volume);
		$authorsSource = $bookInfoXml->channel->item[0]->author;
		$publishersSource = $bookInfoXml->channel->item[0]->children("dc", true)->publisher;
		$authorsArray = array_filter(str_getcsv($authors), fn($val) => $val !== "");
		$publishersArray = $publishersSource;
		$authorsTemp = "";
		$publishersTemp = "";
		foreach ($authorsArray as $item) {
			$authorsTemp .= "\"" . $item . "\", ";
		}
		foreach ($publishersArray as $item) {
			if (empty($item)) continue;
			$publishersTemp .= "\"" . $item . "\", ";
		}
		$authors = substr($authorsTemp, 0, strlen($authorsTemp) - 2);
		$publishers = substr($authorsTemp, 0, strlen($publishersTemp) - 2);
		$insertQuery = $connection->prepare("INSERT INTO books (isbn, title, title_kana, volume, authors, publishers) VALUES (?, ?, ?, ?, ?, ?);");
		$insertQuery->bind_param("sssiss", $_GET["isbn"], $title, $title_kana, $iVolume, $authors, $publishers);
		$insertQuery->execute();
		$insertQuery->close();
	} else {
		$preparedQuery->close();
		$authorsArray = array_filter(str_getcsv($authors), fn($val) => $val !== "");
		$publishersArray = array_filter(str_getcsv($publishers), fn($val) => $val !== "");
	}
	$bookInfo = [
		"title" => $title,
		"title_kana" => $title_kana,
		"volume" => intval($volume),
		"authors" => $authorsArray,
		"publishers" => $publishersArray
	];
	$data = json_encode($bookInfo, JSON_UNESCAPED_UNICODE);
	$data_hash = hash("sha256", $data);
	if (isset($_SERVER["HTTP_IF_NONE_MATCH"])) {
		//If-None-Match 設定時
		$etags = str_getcsv($_SERVER["HTTP_IF_NONE_MATCH"]);
		foreach ($etags as $etag) {
			if (strtolower($etag) === strtolower($data_hash)) {
				//一致
				http_response_code(304);
				header("Etag: {$data_hash}");
				return;
			}
		}
	}
	http_response_code(200);
	header("Content-Type: application/json");
	header("Etag: {$data_hash}");
	echo $data;
	$connection->close();
}
