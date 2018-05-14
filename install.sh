#!/bin/bash

if [[ $EUID > 0 ]]; then
  echo "Please run as root/sudo"
  exit 1
fi

useradd -r -s /bin/false midnite-modbusd

mkdir -p /var/lib/midnite-modbusd
chown midnite-modbusd:midnite-modbusd /var/lib/midnite-modbusd

cp etc/midnite-modbusd.service /lib/systemd/system
cp etc/midnite-modbusd.conf.sample /etc
chmod +x dist/bin/midnite-modbusd
chmod +x dist/bin/newmodbus
chmod +x dist/bin/midnite-classic-data.phar
cp dist/bin/midnite-modbusd /usr/local/bin
cp dist/bin/newmodbus /usr/local/bin
cp dist/bin/midnite-classic-data.phar /usr/local/bin/midnite-classic-data

echo "Copy /etc/midnite-modbusd.conf.sample to /etc/midnite-modbusd.conf and configure it"
echo "Then service start midnite-modbusd.service"
exit 0