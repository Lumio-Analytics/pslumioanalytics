#!/bin/bash
CURDIR=`pwd`
cd $TMPDIR
rm -rf pslumioanalytics
git clone git@github.com:Lumio-Analytics/pslumioanalytics.git
cd pslumioanalytics/
composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader
cd ..
zip -r9 $CURDIR/dist/pslumioanalytics.zip pslumioanalytics -x@pslumioanalytics/exclude.lst
cd $CURDIR