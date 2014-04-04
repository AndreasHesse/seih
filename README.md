Seih
====

Processing and webservice scripts for the Seih project.

Webservices
===========
The API consists of a range of webservices described below.

Get passiv sensor data
----------------------

Returns data from the large passiv data set. It requires the following parameters

 * startTimestamp (integer) Start of the period to return data from
 * endTimestamp (integer) End of the period to return data from
 * homeId (integer) The homeId to select data for [Value is retrieved from session, but can be overriden if you IP is in the accesslist]
 * sensorNames (commalist) List of sensornames to return data for.
 * numberOfPoints (integer) The number of points returned. If set, the data will be mapped to an equidistant array of this length. The dataset will be linearly interpolated to evaluate it in the grid points. If not set, the original full dataset is returned

Depending on the number of Points and the period, the webservice will either return data from the full dataset, or from pre-computed hourly og daily averages.

Ex:

 https://seih.dk/mithjem/api/passiv/getData.php?startTimestamp=1380585600&endTimestamp=1380685600&sensorNames=z1t,ts1&numberOfPoints=800

Get passiv sensor average
-------------------------

Return average values for a sensor in a home over a given time-period in a certain hours of the day.

 * startHour (integer) First hour to get data from
 * endHour (integer) Last hour to get data from
 * startTimestamp (integer) Start of the period to return data from
 * endTimestamp (integer) End of the period to return data from
 * homeId (integer) The homeId to select data for [Value is retrieved from session, but can be overriden if you IP is in the accesslist]
 * sensorNames (commalist) List of sensornames to return data for.
 * numberOfPoints (integer) The number of points returned. If set, the data will be mapped to an equidistant array of this length. The dataset will be linearly interpolated to evaluate it in the grid points. If not set, the original full dataset is returned

Ex:

 https://seih.dk/mithjem/api/passiv/getAverages.php?startTimestamp=1380585600&endTimestamp=1380685600&startHour=9&endHour=22&sensorName=z1t

Get DMI data
------------

Return data from the DMI data collection

 * startTimestamp (integer) Start of the period to return data from
 * endTimestamp (integer) End of the period to return data from
 * stationId (integer) The station to select data for
 * metricNames (commalist) List of metrics to return.
 * numberOfPoints (integer) The number of points returned. If set, the data will be mapped to an equidistant array of this length. The dataset will be linearly interpolated to evaluate it in the grid points. If not set, the original full dataset is returned

Ex:

 https://seih.dk/mithjem/api/dmi/getData.php?startTimestamp=1380585600&endTimestamp=1380685600%20&stationId=06102&metricNames=te,dp&numberOfPoints=800

Get Fjernvarme data
-------------------

Return fjernvarme data

 * startTimestamp (integer) Start of the period to return data from
 * endTimestamp (integer) End of the period to return data from
 * homeId (integer) The station to select data for [Value is retrieved from session, but can be overriden if you IP is in the accesslist]
 * metricNames (commalist) List of metrics to return. Valid metrcs: "kWh", "m3", "m3xC_frem", "m3xC_tilbage"
 * numberOfPoints (integer) The number of points returned. If set, the data will be mapped to an equidistant array of this length. The dataset will be linearly interpolated to evaluate it in the grid points. If not set, the original full dataset is returned

Ex:

 http://seih.dk/mithjem/api/fjernvarme/getData.php?startTimestamp=1388578840&endTimestamp=1391170840%20&metricNames=m3,kWh&numberOfPoints=10

Get ngf data
------------

Return data from the Naturgas fyn collection

 * startTimestamp (integer) Start of the period to return data from
 * endTimestamp (integer) End of the period to return data from
 * ngf_home (integer) The station to select data for [Value is retrieved from session, but can be overriden if you IP is in the accesslist]
 * numberOfPoints (integer) The number of points returned. If set, the data will be mapped to an equidistant array of this length. The dataset will be linearly interpolated to evaluate it in the grid points. If not set, the original full dataset is returned

Ex:

 https://seih.dk/mithjem/api/ngf/getData.php?startTimestamp=1388578840&endTimestamp=1391170840%20&numberOfPoints=10


ToDo:
-----

 * Caching of data (memcache)
 * Determine if getAverages can be found from MongoData with no aggregation
