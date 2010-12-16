## Copyright (c) 2009 Koen Koning + Thomas van Ophem

## This program is free software. It comes without any warranty, to
## the extent permitted by applicable law. You can redistribute it
## and/or modify it under the terms of the Do What The Fuck You Want
## To Public License, Version 2, as published by Sam Hocevar. See
## http://sam.zoy.org/wtfpl/COPYING for more details.


import sys

try: import MySQLdb
except: sys.exit("ERROR: You need the MySQLdb-module for this to work!")

class DB():
	#Let's initialize the database
	def __init__(self, host, database, user, passwd):
		self.host = host
		self.database = database
		self.user = user
		self.passwd = passwd
		
		self._connect()
		
	#Let's connect to the database..	
	def _connect(self):
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
		"""
		Called when function is not here in our class.
		We pass everything through to our MySQLdb
		"""
		def exc (*arg):
			"""
			Will return the real function, from MySQLdb.
			Will ping before every command,
			so it will automatically reconnect.
			"""
			
			# Uncomment for mysql-debugging!
			#print '\tMySQLdb.' + attr + repr(arg)
			
			func = getattr(self.db, attr)
			try:
				dbfunc = func(*arg)
			except MySQLdb.OperationalError, message:
				if message[0] == 2006: #Mysql has gone away
					self._connect()
					func = getattr(self.db, attr)
					dbfunc = func(*arg)
				else: #Some other error we don't care about
					raise MySQLdb.OperationalError, message
					
			return dbfunc
		
		return exc
		
		
		
	class DBConnectionError(Exception):
		def __init__(self, value):
			self.value = value
		def __str__(self):
			return repr(self.value)

