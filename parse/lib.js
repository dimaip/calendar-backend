'use strict';

module.exports.generateDatesArray = () => {
	const dateStart = process.argv[2] ? new Date(process.argv[2]) : new Date();
	const dateEnd = process.argv[3] ? new Date(process.argv[3]) : new Date(dateStart.valueOf() + 7 * 864e5);
	const dates = [];
	for (var date = dateStart; date.getTime() < dateEnd.getTime(); date = new Date(date.getTime() + (24 * 60 * 60 * 1000))) {
		dates.push(date);
	}
	return dates;
};
