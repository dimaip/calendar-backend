const path = require('path');
const request = require('request');
const FS = require('q-io/fs');
const fs = require('fs');
const dateFormat = require('dateformat');

if (!fs.existsSync('raw')) {
    fs.mkdirSync('raw');
}

module.exports = date => {
    const dateString = dateFormat(date, 'yyyy-mm-dd');
    const url =
        'http://days.pravoslavie.ru/Days/' +
        dateFormat(new Date(date - 13 * 24 * 60 * 60 * 1000), 'yyyymmdd') +
        '.html'; // Julian caledar correction
    console.log(dateString);

    new Promise(function(resolve) {
        request(
            {
                uri: url,
            },
            function(error, response, body) {
                const filePath = path.join('raw', dateString);
                resolve(
                    FS.exists(filePath)
                        .then(value => (value ? console.log('file exists') : FS.write(filePath, body)))
                        .catch(error => console.log(error))
                );
            }
        );
    });
};
