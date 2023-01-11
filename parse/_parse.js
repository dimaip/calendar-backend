const lib = require('./lib.js');
const parse = require('./parse.js');

const dates = lib.generateDatesArray();

dates.map(dateString => parse(dateString));
