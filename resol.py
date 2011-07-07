#!/usr/bin/env python2

# 
# Talk with the Resol DeltaSol BS Plus over the LAN
#
# Currently, it requests the current stats, and puts those in a MySQL database.
#
# Huge thanks to Resol for releasing the specifications of their VBus protocol:
#
#  http://hobbyelektronik.org/w/images/0/04/VBus-Protokollspezifikation.pdf
#  http://resol-vbus.googlegroups.com/web/VBus_Protokol_en_20071218.pdf
#
# I got all my information from the first one mostly, but beware, it's written
# in german... Couldn't find an english version...
#

import socket

# Our own config (vbus lan + database settings)
try: import config
except: sys.exit("ERROR: config.py not found! Copy config.example.py for a template.")

from database import DB

"""Logs in onto the DeltaSol BS Plus over LAN. Also starts (and maintains) the
actual stream of data."""
def login():
    dat = recv()

    if dat != "+HELLO\n": return
    
    send("PASS %s\n" % config.vbus_pass)
    
    dat = recv()
    
    if not dat.startswith("+OK"): return
    
    send("DATA\n")
    
    dat = recv()
    
    if not dat.startswith("+OK"): return
    
    print "Here we go....."
    print
    print
    
    buf = recv()
    
    while parsestream(buf): buf += recv()
    
"""Receives 1024 bytes from the stream. Adds debug."""
def recv():
    print "Receiving..."
    print sock
    dat = sock.recv(1024)
    print "< '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    
    return dat
    
"""Sends given bytes over the stram. Adds debug."""
def send(dat):
    print "> '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    sock.send(dat)
    
"""Parses the given stream, and searches for VBus messages (by looking for the
SYNC-byte 0xAA). If no usefull messages were found, it will return True (causing
the login to read more data from the stream). Otherwise returns the latest stats
from the device in a dict. Validates data with checksum."""
def parsestream(data):
    if data.count(chr(0xAA)) < 2:
        return True

    usefulldata = None
        
    msgs = data.split(chr(0xAA))[1:-1]
    for msg in msgs:
        print ' '.join([hex(ord(i))[2:] for i in msg])
        
        target =    gb(msg,0,2)
        source =    gb(msg,2,4)
        protocol =  gb(msg,4,5)
        command =   gb(msg,5,7)
        print "Target:\t\t",    hex(target)
        print "Source:\t\t",    hex(source)
        print "Protocol:\t",    hex(protocol)
        print "Command:\t",     hex(command)
        
        if protocol == 0x10:
            print "PROT 1: ",
            if command == 0x0100:
                print "DATA"
                
                frames = gb(msg,7,8)
                chk =    gb(msg,8,9)
                print "Payload frames:\t", hex(frames)
                print "Checksum:\t", hex(chk)
                print "Our chk:\t", hex(getchk(msg[0:8]))
                
                if getchk(msg[0:8]) != chk:
                    print "!!CHECKSUM MISMATH!!\n"
                    continue
                
                payload = msg[9:9+(6*frames)]
                ret = parsepayload(payload)
                
                if ret:
                    usefulldata = ret
                
            if command == 0x0200:
                print "DATA (ANSWER NEEDED)"
            if command == 0x0300:
                print "REQUEST DATA"
        if protocol == 0x20:
            print "PROT 2: ",
            if command == 0x0100:
                print "MODULE ANSWER"
            if command == 0x0200:
                print "VALUE WRITE"
            if command == 0x0300:
                print "VALUE READ"
            if command == 0x0400:
                print "VALUE WRITE (2)"
            if command == 0x0500:
                print "RELEASE MASTER"
            if command == 0x0600:
                print "RELEASE SLAVE"
            
            print
            continue
            
        print
       
        print
     
    if not usefulldata:
        return True
        
    # Stash data in DB
    print "STASHING MA' STASH"
        
"""Parses the individual payload of a stat message. It reads and parses the
frames, each checking their checksum, and injecting their septet byte. It will
return None if a checksum error occured, otherwise a dict with the stats."""
def parsepayload(payload):
    print ' '.join([hex(ord(i))[2:] for i in payload])
    
    data = []
    
    # Numbers are the actual bytes that make up the value, NOT the indices (last
    # one +1 for that)
    payloadmap = {'temp1':  (0, 1),
                  'temp2':  (2, 3),
                  'temp3':  (4, 5),
                  'temp4':  (6, 7),
                  'pump1':  (8, 8),
                  'pump2':  (9, 9),
                  'relais': (10, 10),
                  'errors': (11, 11),
                  'time':   (12, 13),
                  'scheme': (14, 14),
                  'flags':  (15, 15),
                  'r1time': (16, 17),
                  'r2time': (18, 19),
                  'version':(26, 27)
                 }
                  
    
    for i in range(len(payload)/6):
        frame = payload[i*6:i*6+6]
        #print ' '.join([hex(ord(i))[2:] for i in frame])
        
        chk = ord(frame[5])
        ourchk = getchk(frame[:-1])
        if chk != ourchk:
            print "!!FRAME CHECKSUM MISMATCH!!", chk, ourchk
            return None
        
        septet = ord(frame[4])
        
        for j in range(4):
            if septet & (1 << j):
                data.append(chr(ord(frame[j]) | 0x80))
            else:
                data.append(frame[j])
         
    print "injecting septets... ->"
    print ' '.join([hex(ord(i))[2:] for i in data])
    
    
    vals = {}
    for i, rng in payloadmap.items():
        vals[i] = gb(data, rng[0], rng[1]+1)
        
    for i,j in vals.items():
        print "%s\t%s" % (i,j)
        
    return vals
    
        
"""Gets the numerical value of a set of bytes in data between begin and end.
This thing will handle the significance of bytes."""
def gb(data, begin, end): # GetBytes
    return sum([ord(b)<<(i*8) for i,b in enumerate(data[begin:end])])
    
"""Generates a checksum byte for the given set of bytes."""
def getchk(data):   
    chk = 0x7F
    for b in data:
        chk = ((chk - ord(b)) % 0x100) & 0x7F
        
    return chk
    
def saveinDB(data):
    # Connect to MySQL database
    db = DB(config.db['host'], config.db['resol_database'], config.db['user'], config.db['password'])
    
    db.execute("INSERT INTO `resol`(`time`, `t1`, `t2`, `p1`, `relais`, `flags`, `errors`, `rt1`) VALUES "
               "(NULL, '%(temp1)d', '%(temp2)d', '%(pump1)d', '%(relais)d', '%(flags)d', '%(errors)d', '%(r1time)d')" % data)
   



sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

print "Connecting..."
sock.connect(config.address)
print "Connected"

print sock

login()

print "Killing socket..."
try: sock.shutdown(0)
except: pass
sock.close()
sock = None
print "Dead :-("
    

