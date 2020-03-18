#!/bin/bash

composer install
git clone https://github.com/dimaip/bible-translations bible
cd parse
yarn && yarn build
cd ..
