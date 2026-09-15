#!/bin/bash
for f in /proc/[0-9]*/cmdline; do
  p="${f%/cmdline}"
  p="${p#/proc/}"
  c=$(tr '\0' ' ' < "$f" 2>/dev/null)
  echo "$p: $c"
done
