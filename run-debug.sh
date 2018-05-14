#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

dist/bin/midnite-modbusd -w ${DIR}/var/lib/midnite-modbusd -c ${DIR}/etc/midnite-modbusd.conf -l ${DIR}/var/log/midnite-modbusd.log -d -d
