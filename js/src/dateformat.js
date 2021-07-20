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

exports.mformat = function () {
    var sample;

    try {
        sample = window.Intl ? new Intl.DateTimeFormat((document.documentElement.getAttribute("data-locale") || undefined), {
          numberingSystem: 'latn',
          calendar: 'gregory',
        }).format(new Date(1970, 11, 31)) : '';
    } catch {
        sample = window.Intl ? new Intl.DateTimeFormat(undefined, {
          numberingSystem: 'latn',
          calendar: 'gregory',
        }).format(new Date(1970, 11, 31)) : '';
    }

    let mm = 0,
        mi = sample.indexOf(12);
    let dd = 1,
        di = sample.indexOf(31);
    let yy = 2,
        yi = sample.indexOf(1970);

    // IE 10 or earlier, iOS 9 or earlier, non-Latin numbering system
    // or non-Gregorian calendar; fall back to mm/dd/yyyy
    if (yi >= 0 && mi >= 0 && di >= 0) {
      mm = (mi > yi) + (mi > di);
      dd = (di > yi) + (di > mi);
      yy = (yi > mi) + (yi > di);
    }

    let r = [];
    r[yy] = 'YYYY';
    r[mm] = 'MM';

    return r.join(sample.match(/[-.]/) || '/').replace('//','/');
};
