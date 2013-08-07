# Solar

Retrieves data at regular intervals from several solar installations, and saves
these in a database. This data is then viewable through a web interface.

Source code used by http://solar.chozo.nl/.

## Supported devices

There is currently support for a very limited number of devices:

 * The Soladin 600 solar panel collector.
 * The Resol DeltaSol BS Plus using a socket connection over LAN.

## Usage

Once a database has been set up with the correct tables (see database\_dump) the
configs in both the root and the www directory should be created from the
templates. The two python scripts in the root should run periodically using
cron. The www folder should be accessible from a php enabled webserver.
