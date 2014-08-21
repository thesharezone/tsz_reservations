THE SHARE ZONE RESERVATIONS

Add on for The Share Zone Listings plugin, tsz_listings.

Gives hosts the ability to setup spaces within their listing and reservation calendars for each space.

Demo: http://theShare.zone



Requires these PAGES:

"Edit Space" edit-space 



MYSQL:

CREATE TABLE IF NOT EXISTS `tsz_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `start` int(11) DEFAULT NULL,
  `end` int(11) DEFAULT NULL,
  `guest_email` varchar(256) DEFAULT NULL,
  `guest_name` varchar(256) DEFAULT NULL,
  `details` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `tsz_spaces` (
  `listing_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ord` int(11) NOT NULL,
  PRIMARY KEY (`listing_id`,`ord`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


TO DO:

front end filter listings by dates, will require a new hook in the listings index page

activation hook for MySQL and pages