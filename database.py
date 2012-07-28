## Copyright (c) 2009 Koen Koning + Thomas van Ophem

## This program is free software. It comes without any warranty, to
## the extent permitted by applicable law. You can redistribute it
## and/or modify it under the terms of the Do What The Fuck You Want
## To Public License, Version 2, as published by Sam Hocevar. See
## http://sam.zoy.org/wtfpl/COPYING for more details.


import sys

try:
    import MySQLdb
except:
    sys.exit("ERROR: You need the MySQLdb-module for this to work!")

class DB():
    """Creates a wrapper for the MySQLdb class which can handle timeouts.

    The normal MySQLdb class can timeout after a while, and all calls will then
    raise an error. This class serves as a transparent wrapper, passing all
    function calls to the real MySQLdb. When a function call raises an error
    that a ping timeout occured, the connection will be reestablished and the
    function call will be executed again."""

    def __init__(self, host, database, user, passwd):
        self.host = host
        self.database = database
        self.user = user
        self.passwd = passwd

        self._connect()

    def _connect(self):
        """Cretes a connecting to the database, which this class will wrap."""
        try:
            self.dbcon = MySQLdb.Connect(
            host=self.host,
            user=self.user,
            passwd=self.passwd,
            db = self.database)
            self.db = self.dbcon.cursor(MySQLdb.cursors.DictCursor)
        except:
            #sys.exit("Could not connect to the database!")
            raise self.DBConnectionError("Could not connect to the database!")

    def __getattr__(self, attr):
        """Called when function is not here in our class.
        We pass everything through to our MySQLdb."""

        def exc (*arg):
            """Will return the real function, from MySQLdb.

            Will ping before every command, so it will automatically
            reconnect."""

            # Uncomment for mysql-debugging!
            #print '\tMySQLdb.' + attr + repr(arg)

            func = getattr(self.db, attr)
            try:
                dbfunc = func(*arg)
            except MySQLdb.OperationalError, message:
                if message[0] == 2006: # Mysql has gone away
                    self._connect()
                    func = getattr(self.db, attr)
                    dbfunc = func(*arg)
                else: # Some other error we don't care about
                    raise MySQLdb.OperationalError, message

            return dbfunc

        return exc


    class DBConnectionError(Exception):
        def __init__(self, value):
            self.value = value
        def __str__(self):
            return repr(self.value)

