#twitter-archive-parser
A PHP script to parse through your Twitter archive
----------------------------------------------------------
This is a really simple PHP script that chews through your Twitter archive and plops it into a mysql table. Helps with the default JS format that Twitter provides and also tags up the entity data (hashtags, user mentions, etc).


Requirements
------------------
- PHP (version 5 or better)
- JSON extension


Usage
------------------
You will need to set six variables before running
 - mysql host
 - mysql user
 - mysql password
 - mysql database
 - mysql table name
 - timezone identifier [php.net](http://us1.php.net/manual/en/timezones.php)

You will also want to download your twitter archive
 - download from [twitter.com](http://blog.twitter.com/2012/12/your-twitter-archive.html)
 - place unzipped directory in same directory as index.php before running*

(*) If you want to put it somewhere else just modify the $path_pattern parameter


Changelog
------------------
v1.0 (2013-03-29)
 - initial release


------------------
 - Project at GitHub [jacobemerck/twitter-archive-parser](https://github.com/jacobemerick/twitter-archive-parser)
 - Jacob Emerick [@jpemeric](http://twitter.com/jpemeric) [jacobemerick.com](http://home.jacobemerick.com/)
