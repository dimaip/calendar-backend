const lib = require('./lib.js');
const parse = require('./parse.js');
const FS = require('q-io/fs');

const dates = lib.generateDatesArray();

dates.map(dateString => parse(dateString))
