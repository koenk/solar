#!/usr/bin/env python2

# 
# Talk with the Soladin 600 over the serial port.
#
# Currently, it requests the current stats, and puts those in a MySQL database.
#
# Huge credits go to the following website for figuring out the protocol:
#  http://wiki.firestorm.cx/index.php/Soladin
#

import sys, time

from serial import Serial

# Our own config (serial port + database settings)
try: import config
except: sys.exit("ERROR: config.py not found! Copy config.example.py for a template.")

from database import DB

exe="stats"

commands = {'probe':            "00 00 00 00 C1 00 00 00 C1",
            'firmware':         "11 00 00 00 B4 00 00 00 C5",
            'stats':            "11 00 00 00 B6 00 00 00 C7",
            'max_power':        "11 00 00 00 B9 00 00 00 CA",
            'reset_max_power':  "11 00 00 00 97 01 00 00 A9",
            'history':          "11 00 00 00 9A 00 00 00 AB" } # byte 6 = day
            
# Length of answers of each command (inclusive last byte, checksum)
com_length = {  'probe':            9,
                'firmware':         31,
                'stats':            31,
                'max_power':        31,
                'reset_max_power':  9,
                'history':          9 }
                
# NOT directly usable indices (last one has to be +1 then)
com_map = { 'stats': {  'flags':        (7, 8),
                        'pv_volt':      (9, 10),
                        'pv_amp':       (11, 12),
                        'grid_freq':    (13, 14),
                        'grid_volt':    (15, 16),
                        'grid_pow':     (19, 20),
                        'total_pow':    (21, 23),
                        'temp':         (24, 24),
                        'optime':       (25, 29) }
          }
        
def printbytes(bytes):
    """
    Prints the given sequence of bytes as hexidecimal values, and the length of
    the entire sequence.
    """
    print "%d: " % len(bytes),
    print ' '.join([hex(ord(b)) for b in bytes])
    
def decode(bytes, command):
    """
    Reads all values from a sequence of bytes, using the map of a given command.
    """
    cmap = com_map[command]
    ret = {}
    for com, rng in cmap.items():
        ret[com] = sum([ord(b)<<(8*i) for i,b in enumerate(bytes[rng[0]-1:rng[1]])])
        
    return ret

# Convert commands to real bytes                
for com, bytes in commands.items():
    commands[com] = ''.join([chr(int(b, 16)) for b in bytes.split(' ')])

# Connect to MySQL database
db = DB(config.db['host'], config.db['database'], config.db['user'], config.db['password'])

# Connect to serial device
ser = Serial(config.device, config.baudrate, timeout=config.timeout)
# Write command
ser.write(commands[exe])
# And wait for answer
read = ser.read(com_length[exe])
ser.close()

print "[%s]" % time.ctime(),

# If the length is 0, and the response was empty, then the soladin is (most
# likely) sleeping because there is no power (at night).
if len(read) > 0:
    printbytes(read)
    dec = decode(read, exe)
    dec['table'] = config.db['table_solar']
    
    # Put it in our MySQL db
    db.execute("INSERT INTO `%(table)s`(`time`, `flags`, `pv_volt`, `pv_amp`, `grid_freq`, `grid_volt`, `grid_pow`, `total_pow`, `temp`, `optime`) VALUES "
               "(NULL, '%(flags)d', '%(pv_volt)d', '%(pv_amp)d', '%(grid_freq)d', '%(grid_volt)d', '%(grid_pow)d', '%(total_pow)d', '%(temp)d', '%(optime)d')" % dec)
else:
    print "Soladin not responding, sun down? :-("
    # HACK: Just write null-data
    db.execute("SELECT `total_pow` FROM `stats` ORDER BY `time` DESC LIMIT 1")
    prev_pow = db.fetchone()['total_pow']
    db.execute("INSERT INTO `stats`(`time`, `flags`, `pv_volt`, `pv_amp`, `grid_freq`, `grid_volt`, `grid_pow`, `total_pow`, `temp`, `optime`, `hasdata`) VALUES "
               "(NULL, '0', '0', '0', '0', '0', '0', '%d', '0', '0', '0')" % prev_pow)

