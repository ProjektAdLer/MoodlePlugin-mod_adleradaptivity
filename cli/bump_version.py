#!/usr/bin/python3
"""Bump the version number in version.php by one.
If the date part is not from today, set it to today and set part_number to 00
"""

import fileinput
import sys
import datetime
import os
import pathlib

version_file = os.path.join(pathlib.Path(__file__).parent.resolve(), "..", 'version.php')

if not os.path.exists(version_file):
    print(f"version.php does not exist at {version_file}. Skipping.")
    exit(1)

for line in fileinput.input(version_file, inplace=True):
    if line.startswith("$plugin->version"):
        version = line.split("=")[1].strip()[:-1]

        part_date = version[:8]
        part_number = version[8:]

        old_date = datetime.datetime.strptime(part_date, "%Y%m%d")
        # check if this is today
        if old_date.date() == datetime.date.today():
            # increment part_number
            part_number = str(int(part_number) + 1)
            # to string with leading zeros
            part_number = part_number.zfill(2)
        else:
            part_date = datetime.date.today().strftime("%Y%m%d")
            part_number = "00"

        print(f"$plugin->version = {part_date}{part_number};")
    else:
        print(line, end='')

print(f"Version bumped for {version_file}.")
