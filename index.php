<?php


// first, let's set up some configuration
$mysql_host = '';
$mysql_user = '';
$mysql_password = '';
$mysql_database = '';
$mysql_table = '';
$timezone = '';

date_default_timezone_set($timezone);

$hashtag_link_pattern = '<a href="http://twitter.com/search?q=%%23%s&src=hash" rel="nofollow" target="_blank">#%s</a>';
$url_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';
$user_mention_link_pattern = '<a href="http://twitter.com/%s" rel="nofollow" target="_blank" title="%s">@%s</a>';
$media_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';


// okay, now time to connect and set up the table
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database);
if($mysqli->connect_error !== null)
	exit("Oh my, there was a connection issue. Error: {$mysqli->connect_error}");

$create_table_query = "
	CREATE TABLE IF NOT EXISTS `{$mysql_table}`
	(
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`tweet_id` BIGINT(20) NOT NULL,
		`date` DATETIME NOT NULL,
		`text` TEXT NOT NULL,
		`is_reply` TINYINT(1) NOT NULL,
		`is_mention` TINYINT(1) NOT NULL,
		`screen_name` VARCHAR(15) NOT NULL,
		`data` TEXT NOT NULL,
		PRIMARY KEY(`id`),
		UNIQUE(`tweet_id`)
	)";

if($mysqli->query($create_table_query) === false)
	exit("Huh, setting up the table failed. Error: {$mysqli->error}");

$insert_statement = $mysqli->prepare("INSERT INTO `{$mysql_table}` (`tweet_id`, `date`, `text`, `is_reply`, `is_mention`, `screen_name`, `data`) VALUES (?, ?, ?, ?, ?, ?, ?)");
if($insert_statement === false)
	exit('There was an error with the prepare statement.');


// aight, time to loop through the js files and input some data
$path_pattern = '';
$path_pattern .= dirname(__FILE__);
$path_pattern .= DIRECTORY_SEPARATOR;
$path_pattern .= 'tweets';
$path_pattern .= DIRECTORY_SEPARATOR;
$path_pattern .= 'data';
$path_pattern .= DIRECTORY_SEPARATOR;
$path_pattern .= 'js';
$path_pattern .= DIRECTORY_SEPARATOR;
$path_pattern .= 'tweets';
$path_pattern .= DIRECTORY_SEPARATOR;
$path_pattern .= '*';

$file_array = glob($path_pattern);

if($file_array === false || count($file_array) < 1)
	exit('Could not find any files to parse. Please check your file structure and the readme.');

$insert_count = 0;

foreach($file_array as $filename)
{
	$handle = fopen($filename, 'rb');
	$tweet_data = fread($handle, filesize($filename));
	fclose($handle);
	
	if($tweet_data === false || strlen($tweet_data) < 1)
		exit("There was a problem reading one of the files. Please check it out; {$filename}");
	
	// oh yeah, it's set up up for a js object. let's get rid of that
	$tweet_data = preg_filter('/^Grailbird\.data\.tweets_[0-9]{4}_[0-9]{2}\s=/', '', $tweet_data);
	$tweet_data = json_decode($tweet_data);
	
	foreach($tweet_data as $tweet)
	{
		$tweet_id = $tweet->id_str;
		$date = date('Y-m-d H:i:s', strtotime($tweet->created_at));
		
		$text = $tweet->text;
		
		// let's make the tweet pretty
		$entity_holder = array();
		
		foreach($tweet->entities as $type => $entity)
		{
			if(is_array($entity) && count($entity) < 1)
				continue;
			
			$entity = array_pop($entity);
			
			$entity_array = array();
			$entity_array['start'] = $entity->indices[0];
			$entity_array['length'] = $entity->indices[1] - $entity->indices[0];
			
			switch($type)
			{
				case 'hashtags' :
					$entity_array['replace'] = sprintf($hashtag_link_pattern, strtolower($entity->text), $entity->text);
					break;
				case 'urls' :
					$entity_array['replace'] = sprintf($url_link_pattern, $entity->url, $entity->expanded_url, $entity->display_url);
					break;
				case 'user_mentions' :
					$entity_array['replace'] = sprintf($user_mention_link_pattern, strtolower($entity->screen_name), $entity->name, $entity->screen_name);
					break;
				case 'media' :
					$entity_array['replace'] = sprintf($media_link_pattern, $entity->url, $entity->expanded_url, $entity->display_url);
					break;
				default :
					exit("Unexpected entity found! Key: {$type} in tweet: {$tweet->id}");
					break;
			}
			
			$entity_holder[$entity->indices[0]] = $entity_array;
		}
		
		krsort($entity_holder);
		foreach($entity_holder as $entity)
		{
			$text = substr_replace($text, $entity['replace'], $entity['start'], $entity['length']);
		}
		
		$is_mention = (isset($tweet->in_reply_to_screen_name) && strlen($tweet->in_reply_to_screen_name) > 0) ? 1 : 0;
		$is_retweet = (isset($tweet->retweeted_status) && $tweet->retweeted_status != '') ? 1 : 0;
		$screen_name = $tweet->user->screen_name;
		$raw_object = json_encode($tweet);
		
		if(
			$insert_statement->bind_param(
				'sssiiss',
				$tweet_id,
				$date,
				$text,
				$is_mention,
				$is_retweet,
				$screen_name,
				$raw_object) === false)
			exit("The parameters could not be bound to the statement. Tweet: {$tweet_id}");
		
		if($insert_statement->execute() === false)
			exit("The statement could not be executed. Tweet: {$tweet_id} Error: {$insert_statement->error}");
		$insert_count++;
		
		$insert_statement->reset();
	}
}

echo "Congratulations, you just inserted {$insert_count} tweets. You deserve a cookie.";
$insert_statement->close();
$mysqli->close();