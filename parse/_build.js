const lib = require('./lib.js');
const scrape = require('./scrape.js');
const parse = require('./parse.js');

const dates = lib.generateDatesArray();

const RateLimiter = require('limiter').RateLimiter;
const limiter = new RateLimiter(1, 1000);

const promises = dates.map(dateString => {
  return new Promise(function (resolve) {
    limiter.removeTokens(1, () => resolve(scrape(dateString)))
  })
})
Promise.all(promises).then(() =>
  dates.map(dateString => parse(dateString))
);
