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
		echo "{}";
		return;
	}
	if ($preparedQuery->num_rows != 1 && empty($title)) {
		$preparedQuery->close();
		$bookInfoRaw = file_get_contents(BASE_ADDRESS . $_GET["isbn"]);
		$bookInfoXml = simplexml_load_string($bookInfoRaw);
		if ($bookInfoXml == null) {
			http_response_code(500);
			header("Content-Type: application/json");
			echo "{}";
			return;
		}
		if (intval($bookInfoXml->rss->channel->{"openSearch:totalResults"}, 10) == 0) {
			http_response_code(404);
			header("Content-Type: application/json");
			echo "{}";
			return;
		}
		$title = $bookInfoXml->rss->channel->item[0]->title;
		$title_kana = $bookInfoXml->rss->channel->item[0]->{"dcndl:titleTranscription"};
		$volume = $bookInfoXml->rss->channel->item[0]->{"dcndl:volume"};
		$iVolume = intval($volume);
		$authorsSource = $bookInfoXml->rss->channel->item[0]->author;
		$publishersSource = $bookInfoXml->rss->channel->item[0]->{"dc:publisher"};
		$authorsArray = array_filter(str_getcsv($authors), fn($val) => $val !== "");
		$publishersArray = array_filter($publishersSource, fn($val) => $val !== "");
		$authorsTemp = "";
		$publishersTemp = "";
		foreach ($authorsArray as $item) {
			$authorsTemp .= "\"" . $item . "\", ";
		}
		foreach ($publishersArray as $item) {
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
