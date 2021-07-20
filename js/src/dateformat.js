var moment = require("moment");
moment.locale(document.documentElement.getAttribute("data-locale") || undefined);
const dformat = moment.localeData().longDateFormat('L');
const tformat = moment.localeData().longDateFormat('LTS');

exports.dformat = function () {
    return dformat;
};

exports.tformat = function () {
    return tformat;
};

exports.dtformat = function () {
    return dformat + ' ' + tformat;
};
