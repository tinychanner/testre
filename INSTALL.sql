-- Import this SQL file to install ATBBS.

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


-- Create tables:

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `uid` varchar(23) NOT NULL,
  `time` int(10) NOT NULL,
  `action_name` varchar(60) NOT NULL,
  `action_id` int(10) NOT NULL,
  PRIMARY KEY  (`uid`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `failed_postings`
--

CREATE TABLE `failed_postings` (
  `uid` varchar(23) NOT NULL,
  `time` int(10) NOT NULL,
  `reason` text NOT NULL,
  `headline` varchar(100) NOT NULL,
  `body` text NOT NULL,
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ignore_lists`
--

CREATE TABLE `ignore_lists` (
  `uid` varchar(23) NOT NULL,
  `ignored_phrases` text NOT NULL,
  PRIMARY KEY  (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE IF NOT EXISTS `images` (
  `file_name` varchar(80) NOT NULL,
  `md5` varchar(32) NOT NULL,
  `topic_id` int(10) unsigned DEFAULT NULL,
  `reply_id` int(10) unsigned DEFAULT NULL,
  UNIQUE KEY `reply_id` (`reply_id`),
  UNIQUE KEY `topic_id` (`topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ip_bans`
--

CREATE TABLE `ip_bans` (
  `ip_address` varchar(100) NOT NULL,
  `filed` int(10) NOT NULL,
  `expiry` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `last_actions`
--

CREATE TABLE `last_actions` (
  `feature` varchar(30) NOT NULL,
  `time` int(11) NOT NULL,
  PRIMARY KEY  (`feature`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `url` varchar(100) NOT NULL,
  `page_title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `replies`
--

CREATE TABLE `replies` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `parent_id` int(10) NOT NULL,
  `poster_number` int(10) NOT NULL,
  `author` varchar(23) character set latin1 NOT NULL,
  `author_ip` varchar(100) character set latin1 NOT NULL,
  `time` int(10) NOT NULL,
  `body` text character set latin1 NOT NULL,
  `edit_time` int(10) default NULL,
  `edit_mod` tinyint(1) default NULL,
  PRIMARY KEY  (`id`),
  KEY `parent_id` (`parent_id`,`author`,`author_ip`),
  KEY `letter` (`poster_number`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `time` int(10) unsigned NOT NULL,
  `author` varchar(23) character set latin1 collate latin1_spanish_ci NOT NULL,
  `author_ip` varchar(100) character set latin1 NOT NULL,
  `replies` int(10) NOT NULL,
  `last_post` int(10) NOT NULL,
  `visits` int(10) NOT NULL default '0',
  `headline` varchar(100) character set latin1 NOT NULL,
  `body` text character set latin1 NOT NULL,
  `edit_time` int(10) default NULL,
  `edit_mod` tinyint(1) default NULL,
  PRIMARY KEY  (`id`),
  KEY `author` (`author`),
  KEY `author_ip` (`author_ip`),
  KEY `last_post` (`last_post`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `trash`
--

CREATE TABLE `trash` (
  `uid` varchar(23) NOT NULL,
  `time` int(10) NOT NULL,
  `headline` varchar(100) NOT NULL,
  `body` text NOT NULL,
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `uid_bans`
--

CREATE TABLE `uid_bans` (
  `uid` varchar(23) NOT NULL,
  `filed` int(10) NOT NULL,
  PRIMARY KEY  (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `uid` varchar(23) character set latin1 NOT NULL,
  `password` varchar(32) character set latin1 NOT NULL,
  `first_seen` int(10) NOT NULL,
  `ip_address` varchar(100) character set latin1 NOT NULL,
  PRIMARY KEY  (`uid`),
  KEY `first_seen` (`first_seen`),
  KEY `ip_address` (`ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `uid` varchar(23) character set latin1 NOT NULL,
  `memorable_name` varchar(100) character set latin1 NOT NULL,
  `memorable_password` varchar(100) character set latin1 NOT NULL,
  `email` varchar(100) character set latin1 NOT NULL,
  `spoiler_mode` tinyint(1) NOT NULL default '0',
  `snippet_length` smallint(3) NOT NULL default '80',
  `topics_mode` tinyint(1) NOT NULL,
  `ostrich_mode` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`uid`),
  KEY `memorable_name` (`memorable_name`),
  KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `watchlists`
--

CREATE TABLE `watchlists` (
  `uid` varchar(23) NOT NULL,
  `topic_id` int(10) NOT NULL,
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------


-- Insert some needed rows

INSERT INTO `last_actions` (`feature`, `time`) VALUES
('last_bump', 0),
('last_topic', 0);

INSERT INTO `pages` (`id`, `url`, `page_title`, `content`) VALUES
(1, 'FAQ', 'Frequently Asked Questions', '<h4>How do I edit the FAQ?</h4>\n<p>Use the <a href="/CMS">content manager</a>.</p>'),
(2, 'markup_syntax', 'Markup syntax', '<table>\r\n<thead>\r\n<tr>\r\n<th class="minimal">Output</th>\r\n<th>Input</th>\r\n</tr>\r\n</thead>\r\n<tbody>\r\n\r\n<tr class="odd">\r\n<td class="minimal"><em>Emphasis</em></td>\r\n<td><kbd>''''Emphasis''''</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class="minimal"><strong>Strong emphasis</strong></td>\r\n<td><kbd>''''''Strong emphasis''''''</kbd></td>\r\n</tr>\r\n\r\n<tr class="odd">\r\n<td class="minimal"><h4 class="user">Header</h4></td>\r\n<td><kbd>==Header==</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class="minimal"><span class="quote"><strong>></strong> Quote</span></td>\r\n<td><kbd>> Quote</kbd></td>\r\n</tr>\r\n\r\n\r\n<tr>\r\n<td class="minimal"><a href="http://example.com/">Link text</a></td>\r\n<td><kbd>[http://example.com/ Link text]</kbd></td>\r\n</tr>\r\n\r\n<tr>\r\n<td class="minimal"><span class="quote"><strong>></strong> Block</span><br /><span class="quote"><strong>></strong> quote</span></td>\r\n<td><kbd>[quote]Block<br />quote[/quote]</kbd></td>\r\n</tr>\r\n\r\n</tbody>\r\n</table>');