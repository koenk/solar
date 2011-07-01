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
    
    parsebytes(recv())
    
def recv():
    print "Receiving..."
    print sock
    dat = sock.recv(1024)
    print "< '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    
    return dat
    
def send(dat):
    print "> '%s' (%s)" % (dat, ' '.join([str(ord(i)) for i in dat]))
    sock.send(dat)
    
def parsebytes(data):
    msgs = data.split(chr(0xAA))[1:-1]
    for msg in msgs:
        print ' '.join([hex(ord(i))[2:] for i in msg])
        
        target =    gb(msg,0,2)
        source =    gb(msg,2,4)
        protocol =  gb(msg,4,5)
        command =   gb(msg,5,7)
        print "Target:\t\t",    hex(target)
        print "Source:\t\t",    hex(source)
        print "Protocol:\t",  hex(protocol)
        print "Command:\t",   hex(command)
        
        if protocol == 0x10:
            print "PROT 1: ",
            if command == 0x0100:
                print "DATA"
                
                frames = gb(msg,7,8)
                print "Data frames:\t", hex(frames)
                
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
        
def gb(data, begin, end): # GetBytes
    return sum([ord(b)<<(i*8) for i,b in enumerate(data[begin:end])])



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
    

