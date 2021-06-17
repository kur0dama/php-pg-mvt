# php-pg-mvt

---

A PHP adaptation of @pramsey's minimal-mvt in Python ([here](https://github.com/pramsey/minimal-mvt)). Provides a simple connection to a PostgreSQL/PostGIS table via a REST API, returns Mapbox Vector Tiles (or could easily be modified to return GeoJSON) for display in Mapbox, Leaflet, etc. There are other more comprehensive solutions, such as [Martin](https://github.com/urbica/martin).

Tested with the following:

- PostgreSQL 10 and 12
- PostGIS 3
- Apache 2.4.26
- PHP 7.4.12

Includes the following:

- The ported tileserver code
- A simple index.php to field calls to tile server via REST API
- A sample .htaccess file to route all REST API calls to index.php
- A sample .ini file for PGSQL

#### Configuration Notes

This repo is intended to be simple and generic. It can be easily modified to accommodate any use case. Some of the most basic, required modifications are:
- The sample .ini file must be populated with the connection details for your PGSQL instance.
- The "envelope_to_sql" method in php_pg_mvt.php develops the query that is sent to PGSQL. At minimum, it should be modified to pull the correct column of geometry/geography data from the requested table and appropriately convert between projections, if necessary. It can also obviously be modified to allow for WHERE conditions, non-geometry data parameters, etc.
- The index.php file is intended solely as a bare-bones illustration of how one might use the tileserver. It does not safely handle CORS, does not gracefully manage errors, and doesn't do much of anything other than serve tiles. In short: **use it only as a guide.**