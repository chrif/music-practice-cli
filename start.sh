pushd $(dirname "${0}") > /dev/null
basedir=$(pwd -L)
# Use "pwd -P" for the path without links. man bash for more info.
popd > /dev/null

php -d date.timezone="America/Montreal" ${basedir}/api.php start "BWV 639"