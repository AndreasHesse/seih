CREATE TABLE `hourly` (
  `homeId` int(10) unsigned NOT NULL DEFAULT '0',
  `sensorName` char(10) NOT NULL DEFAULT '',
  `hour` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `numberOfSamples` mediumint(8) unsigned DEFAULT NULL,
  `averageValue` float(8,2) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`date`, `homeId`,`sensorName`,`hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `daily` (
  `homeId` int(10) unsigned NOT NULL DEFAULT '0',
  `sensorName` char(10) NOT NULL DEFAULT '',
  `numberOfSamples` mediumint(8) unsigned DEFAULT NULL,
  `averageValue` float(8,2) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`date`, `homeId`,`sensorName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;





