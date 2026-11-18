#!/bin/sh
# Rebuilds native-size WordPress.org PNGs from the portable outlined SVG sources.
# Run from the repository root. Original packages and runtime files are untouched.
set -eu

swift .wordpress-org/branding/build.swift
rsvg-convert -w 128 -h 128 -o .wordpress-org/assets/icon-128x128.png .wordpress-org/assets/icon.svg
rsvg-convert -w 256 -h 256 -o .wordpress-org/assets/icon-256x256.png .wordpress-org/assets/icon.svg

for locale in '' -de -fr -it -es; do
	rsvg-convert -w 772 -h 250 -o ".wordpress-org/assets/banner-772x250${locale}.png" ".wordpress-org/branding/banner${locale}.svg"
	rsvg-convert -w 1544 -h 500 -o ".wordpress-org/assets/banner-1544x500${locale}.png" ".wordpress-org/branding/banner${locale}.svg"
done

python3 .wordpress-org/branding/verify.py
