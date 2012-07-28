# Config used by the python scripts used to fetch data from devices.
# Rename this file to config.py before use!

# The solar fetch script supports multiple devices. Both the DB argument and the
# serial devices argument accept an array. The first entry in the devices array
# will be put in the first entry of the tables array.

# MySQL database
db = {}
db['host'] = "localhost"
db['database'] = "<database>"
db['table_solar'] = ["<solar table>", ...]
db['table_resol'] = "<resol table>"
db['user'] = "<user>"
db['password'] = "<password>"

# Serial port
devices = ["/dev/ttyS0", ...]
baudrate = 9600
timeout = 5

# LAN
address = ("192.168.2.60", 7053)
vbus_pass = "vbus"

