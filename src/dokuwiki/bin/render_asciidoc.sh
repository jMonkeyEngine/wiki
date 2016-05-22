#!/bin/bash

cd $(dirname $0)/..
WIKI=$(pwd)
export OUTPUT_DIR=$WIKI/../output_asciidoc
export BIN_DIR=$WIKI/bin
INPUT_DIR=$WIKI/data/pages
cd $INPUT_DIR
echo "dokuwiki: $WIKI"
echo "bin: $BIN_DIR"
echo "input: $INPUT_DIR"
echo "output: $OUTPUT_DIR"
find . -depth -name "*.txt" -exec sh -c 'mkdir -p $2/$(dirname $1) && $BIN_DIR/render.php -r asciidoc -f "$1" >"$2/${1%.txt}.adoc"' _ {} $OUTPUT_DIR \;
#FILE="./documentation.txt"
#echo "---"

#$BIN_DIR/render.php -r asciidoc -f "$FILE" 
#>"$OUTPUT_DIR/${FILE%.txt}.adoc"
#cp -R $WIKI/data/media $OUTPUT_DIR/images
