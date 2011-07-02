import socket

def login():
    dat = recv()

    if dat != "+HELLO\n": return
    
    send("PASS vbus\n")
    
    dat = recv()
    
    if not dat.startswith("+OK"): return
    
    send("DATA\n")
    
    dat = recv()
    
    if not dat.startswith("+OK"): return
    
    print "Here we go....."
    print
    print
    
    while parsestream(recv()): pass
    
def recv():
    print "Receiving..."
    print sock
    dat = sock.recv(1024)
    print "< '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    
    return dat
    
def send(dat):
    print "> '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    sock.send(dat)
    
def parsestream(data):
    if data.count(chr(0xAA)) < 2:
        return True

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
                
                payload = msg[9:9+(6*frames)]
                parsepayload(payload)
                
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
        
        
def parsepayload(payload):
    print ' '.join([hex(ord(i))[2:] for i in payload])
    
    data = []
    
    for i in range(len(payload)/6):
        frame = payload[i*6:i*6+6]
        print ' '.join([hex(ord(i))[2:] for i in frame])
        
        septet = ord(frame[4])
        
        for j in range(4):
            if septet & (1 << j):
                data.append(chr(ord(frame[j]) | 0x80))
            else:
                data.append(frame[j])
                
    print ' '.join([hex(ord(i))[2:] for i in data])
    
    temp1 = gb(data,0,2)
    temp2 = gb(data,2,4)
    temp3 = gb(data,4,6)
    temp4 = gb(data,6,8)
    pump1 = gb(data,8,9)
    pump2 = gb(data,9,10)
    
    print "Temp1:\t", temp1, hex(temp1)
    print "Temp2:\t", temp2, hex(temp2)
    print "Temp3:\t", temp3, hex(temp3)
    print "Temp4:\t", temp4, hex(temp4)
    print "Pump1:\t", pump1, hex(pump1)
    print "Pump2:\t", pump2, hex(pump2)
    
        
def gb(data, begin, end): # GetBytes
    return sum([ord(b)<<(i*8) for i,b in enumerate(data[begin:end])])
    
def getchk(data):   
    chk = 0x7F
    for b in data:
        chk = ((chk - ord(b)) % 0x100) & 0x7F
        
    return chk
   



sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

print "Connecting..."
sock.connect(("192.168.2.60", 7053))
print "Connected"

print sock

login()

print "Killing socket..."
try: sock.shutdown(0)
except: pass
sock.close()
sock = None
print "Dead :-("
    

