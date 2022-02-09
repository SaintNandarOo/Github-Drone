<?php

ini_set( 'display_errors', 1 );
date_default_timezone_set( 'UTC' );
define( 'GOOGLE_NEWS_FEED_URL',  'https://news.google.com/news?' );
define( 'FEED_REQUEST_USERAGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:45.0) Gecko/20100101 Firefox/45.0' );

$result = array();
if ( isset( $_REQUEST['querys'] ) ) {
	$querys = $_REQUEST['querys'];
	if ( ! is_array( $querys ) ) {
		$json = json_decode( $querys );
		$querys = (array) ( $json ?: $querys );
	}
	foreach ( $querys as $query ) {
		$items = get_google_news_feed( $query, $reached_url );
		if ( isset( $items['error'] ) ) {
			$result[] = array_merge( array(
				'query' => $query,
			), $items );
			break;
		}
		$result[] = array(
			'query' => $query,
			'items' => $items,
		);
	}
} else {
	$result['error'] = 'parameter error.';
}

header( 'Content-Type: application/json; charset=utf-8;' );

echo json_encode( $result );


/**
 * Make Google News Feed URL
 *
 * @param string $query
 * @return string
 */
function get_google_news_feed_url( $query ) {
	$params = array(
		'cf' => 'all',
		'hl' => 'en',
		'ned' => 'us',
		'q' => $query,
		'tbm' => 'nws',
		'output' => 'rss',
	);
	return GOOGLE_NEWS_FEED_URL . http_build_query( $params );
}

/*
HTTP/1.1 200 OK
Content-Type: application/rss+xml
P3P: CP="This is not a P3P policy! See https://support.google.com/accounts/answer/151657?hl=en for more info."
Strict-Transport-Security: max-age=31536000
Expires: Thu, 09 Mar 2017 01:32:35 GMT
Date: Thu, 09 Mar 2017 01:31:35 GMT
Cache-Control: private
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Server: GSE
Set-Cookie: 
Alt-Svc: quic=":443"; ma=2592000; v="36,35,34"
Accept-Ranges: none
Vary: Accept-Encoding
Transfer-Encoding: chunked
*/

/**
 * Get Google News Feed
 *
 * @param string $query Search Query
 * @param string $reached_url
 * @return array
 */
function get_google_news_feed( $query, &$reached_url ) {
	$url = get_google_news_feed_url( $query );

	$ch = curl_init( $url );
	//curl_setopt( $ch, CURLOPT_HEADER, true );
	curl_setopt( $ch, CURLOPT_USERAGENT, FEED_REQUEST_USERAGENT );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
	//curl_setopt( $ch, CURLOPT_NOBODY, true );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

	$body = curl_exec( $ch );

	$http_info = curl_getinfo( $ch );

	curl_close( $ch );

	$reached_url = $http_info['url'];
	$http_code = $http_info['http_code'];

	if ( $http_code >= 400 ) {
		return  array( 'error' => 'HTTP Status Error.', 'code' => $http_code );
	}

	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $body );

	if ( $xml === false ) {
		return array( 'error' => 'XML parse error.' );
	}
	//var_dump( $xml );

	$items = array();
	$index = 0;
	foreach ( $xml->channel->item as $item ) {
		$title_raw = (string) $item->title;
		$date_raw = (string) $item->pubDate;
		$link_raw = (string) $item->link;
		//var_dump($index, $title_raw, $date_raw, $link_raw);
		$title_parts = explode( ' - ', $title_raw );
		$source = array_pop( $title_parts );
		$title = htmlspecialchars_decode( implode( ' - ', $title_parts ), ENT_QUOTES );
		parse_str( htmlspecialchars_decode( $link_raw ), $link_param );
		$link = isset( $link_param['url'] ) ? $link_param['url'] : htmlspecialchars_decode( $link_raw );
		$pub_date_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $date_raw ) );
		//var_dump($index, $title, $source, $pub_date_gmt, $link);
		$items[] = array(
			'index' => $index,
			'title' => $title,
			'source' => $source,
			'link' => $link,
			'pub_date_gmt' => $pub_date_gmt
		);
		$index++;
	}
	return $items;
}

?>
