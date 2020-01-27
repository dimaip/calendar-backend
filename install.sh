#!/bin/bash

git clone https://github.com/dimaip/bible-translations bible
cd parse
yarn && yarn build
cd ..
