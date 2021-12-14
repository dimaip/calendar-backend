const sqlite3 = require('sqlite3').verbose();
const groupBy = require('lodash.groupby');
const fs = require('fs').promises;

const bookNames = {
    Быт: 'Быт. Быт Бт. Бт Бытие Ge. Ge Gen. Gen Gn. Gn Genesis',
    Исх: 'Исх. Исх Исход Ex. Ex Exo. Exo Exod. Exod Exodus',
    Лев: 'Лев. Лев Лв. Лв Левит Lev. Lev Le. Le Lv. Lv Levit. Levit Leviticus',
    Чис: 'Чис. Чис Чс. Чс Числ. Числ Числа Nu. Nu Num. Num Nm. Nm Numb. Numb Numbers',
    Втор: 'Втор. Втор Вт. Вт Втрзк. Втрзк Второзаконие De. De Deut. Deut Deu. Deu Dt. Dt  Deuteron. Deuteron Deuteronomy',
    Нав: 'Иис.Нав. Иис.Нав Ис.Нав. Ис.Нав Нав. Нав Иисус Навин Jos. Jos Josh. Josh Joshua',
    Суд: 'Суд. Суд Сд. Сд Судьи Jdg. Jdg Judg. Judg Judge. Judge Judges',
    Руфь: 'Руф. Руф Рф. Рф Руфь Ru. Ru Ruth Rth. Rth Rt. Rt',
    '1Цар': '1Цар. 1Цар 1Цр. 1Цр 1Ц 1Царств. 1Царств 1Sa. 1Sa 1S. 1S 1Sam. 1Sam 1Sm. 1Sm 1Sml. 1Sml 1Samuel',
    '2Цар': '2Цар. 2Цар 2Цр. 2Цр 2Ц 2Царств. 2Царств 2Sa. 2Sa 2S. 2S 2Sam. 2Sam 2Sm. 2Sm 2Sml. 2Sml 2Samuel',
    '3Цар': '3Цар. 3Цар 3Цр. 3Цр 3Ц 3Царств. 3Царств 1Ki. 1Ki 1K. 1K 1Kn. 1Kn 1Kg. 1Kg 1King. 1King 1Kng. 1Kng 1Kings',
    '4Цар': '4Цар. 4Цар 4Цр. 4Цр 4Ц 4Царств. 4Царств 2Ki. 2Ki 2K. 2K 2Kn. 2Kn 2Kg. 2Kg 2King. 2King 2Kng. 2Kng 2Kings',
    '1Пар': '1Пар. 1Пар 1Пр. 1Пр 1Chr. 1Chr 1Ch. 1Ch 1Chron. 1Chron',
    '2Пар': '2Пар. 2Пар 2Пр. 2Пр 2Chr. 2Chr 2Ch. 2Ch 2Chron. 2Chron',
    '1Езд': 'Ездр. Ездр Езд. Езд Ез. Ез Ездра Ezr. Ezr Ezra',
    Неем: 'Неем. Неем. Нм. Нм Неемия Ne. Ne Neh. Neh Nehem. Nehem Nehemiah',
    '2Езд': '2Ездр. 2Ездр 2Езд. 2Езд 2Ездра 2Ездры 2Ез 2Ез.',
    Тов: 'Тов. Тов Товит',
    Иудф: 'Иудиф. Иудиф Иудифь',
    Есф: 'Есф. Есф Ес. Ес Есфирь Esth. Esth Est. Est Esther Es Es.',
    Иов: 'Иов. Иов Ив. Ив Job. Job Jb. Jb',
    Пс: 'Пс. Пс Псалт. Псалт Псал. Псал Псл. Псл Псалом Псалтирь Псалмы Ps. Ps Psa. Psa Psal. Psal Psalm Psalms',
    Прит: 'Прит. Прит Притч. Притч Пр. Пр Притчи Притча Pr. Pr Prov. Prov Pro. Pro Proverb Proverbs',
    Еккл: 'Еккл. Еккл Ек. Ек Екк. Екк Екклесиаст Ec. Ec Eccl. Eccl Ecc. Ecc Ecclesia. Ecclesia',
    Песн: 'Песн. Песн Пес. Пес Псн. Псн Песн.Песней Песни Song. Song Songs SS. SS Sol. Sol',
    Прем: 'Прем.Сол. Премудр.Соломон. Премудр.Сол. Премудр. Прем. Премудр.Соломона Премудрость Премудрости Прем',
    Сир: 'Сир. Сир Сирах',
    Ис: 'Ис. Ис Иса. Иса Исаия Исайя Isa. Isa Is. Is Isaiah',
    Иер: 'Иер. Иер Иерем. Иерем Иеремия Je. Je Jer. Jer Jerem. Jerem Jeremiah',
    Плач: 'Плач. Плач Плч. Плч Пл. Пл Пл.Иер. Пл.Иер Плач Иеремии La. La Lam. Lam Lament. Lament Lamentation Lamentations',
    Посл: 'Посл.Иер. Посл.Иер Посл.Иерем. Посл.Иерем Посл.Иеремии',
    Вар: 'Вар. Вар Варух',
    Иез: 'Иез. Иез Из. Из Иезек. Иезек Иезекииль Ez. Ez Eze. Eze Ezek. Ezek Ezekiel',
    Дан: 'Дан. Дан Дн. Дн Днл. Днл Даниил Da. Da Dan. Dan Daniel',
    Ос: 'Ос. Ос Осия Hos. Hos Ho. Ho Hosea',
    Иоил: 'Иоил. Иоил Ил. Ил Иоиль Joel. Joel Joe. Joe',
    Ам: 'Ам. Ам Амс. Амс Амос Am. Am Amos Amo. Amo',
    Авд: 'Авд. Авд Авдий Ob. Ob Obad. Obad. Obadiah Oba. Oba',
    Ион: 'Ион. Ион Иона Jon. Jon Jnh. Jnh. Jona. Jona Jonah',
    Мих: 'Мих. Мих Мх. Мх Михей Mi. Mi Mic. Mic Micah',
    Наум: 'Наум. Наум Na. Na Nah. Nah Nahum',
    Авв: 'Авв. Авв Аввак. Аввак Аввакум Hab. Hab Habak. Habak Habakkuk',
    Соф: 'Соф. Соф Софон. Софон Софония Zeph. Zeph  Zep. Zep Zephaniah',
    Агг: 'Агг. Агг Аггей Hag. Hag Haggai',
    Зах: 'Зах. Зах Зхр. Зхр Захар. Захар Захария Ze. Ze Zec. Zec Zech. Zech Zechariah',
    Мал: 'Мал. Мал Малах. Малах Млх. Млх Малахия Mal. Mal Malachi',
    '1Ма': '1Макк. 1Макк. 1Маккав. 1Маккав 1Мак. 1Мак 1Маккавейская',
    '2Ма': '2Макк. 2Макк. 2Маккав. 2Маккав 2Мак. 2Мак 2Маккавейская',
    '3Езд': '3Ездр. 3Ездр 3Езд. 3Езд 3Ездра 3Ездры 3Ез 3Ез.',
    Мат: 'Матф. Матф Мтф. Мтф Мф. Мф Мт. Мт Матфея Матфей Мат Мат. Mt. Mt Ma. Ma Matt. Matt Mat. Mat Matthew',
    Мар: 'Мар. Мар Марк. Марк Мрк. Мрк Мр. Мр Марка Мк Мк. Mk. Mk Mar. Mar Mr. Mr Mrk. Mrk Mark',
    Лук: 'Лук. Лук Лк. Лк Лукa Луки Lk. Lk Lu. Lu Luk. Luk Luke',
    Ин: 'Иоан. Иоан Ин. Ин Иоанн Иоанна Jn. Jn Jno. Jno Joh. Joh John',
    Деян: 'Деян. Деян Дея. Дея Д.А. Деяния Ac. Ac Act. Act Acts',
    Рим: 'Рим. Рим Римл. Римл Римлянам Ro. Ro Rom. Rom Romans',
    '1Кор': '1Кор. 1Кор 1Коринф. 1Коринф 1Коринфянам 1Коринфянам 1Co. 1Co 1Cor. 1Cor 1Corinth. 1Corinth 1Corinthians',
    '2Кор': '2Кор. 2Кор 2Коринф. 2Коринф 2Коринфянам 2Коринфянам 2Co. 2Co 2Cor. 2Cor 2Corinth. 2Corinth 2Corinthians',
    Гал: 'Гал. Гал Галат. Галат Галатам Ga. Ga Gal. Gal Galat. Galat Galatians',
    Еф: 'Еф. Еф Ефес. Ефес Ефесянам Eph. Eph Ep. Ep Ephes. Ephes Ephesians',
    Флп: 'Фил. Фил Флп. Флп Филип. Филип Филиппийцам Php. Php Ph. Ph Phil. Phil Phi. Phi. Philip. Philip Philippians',
    Кол: 'Кол. Кол Колос. Колос Колоссянам Col. Col Colos. Colos Colossians',
    '1Фес': '1Фесс. 1Фесс 1Фес. 1Фес 1Фессалоникийцам 1Сол. 1Сол 1Солунянам 1Th. 1Th 1Thes. 1Thes 1Thess. 1Thess 1Thessalonians',
    '2Фес': '2Фесс. 2Фесс 2Фес. 2Фес 2Фессалоникийцам 2Сол. 2Сол 2Солунянам 2Th. 2Th 2Thes. 2Thes 2Thess. 2Thess 2Thessalonians',
    '1Тим': '1Тим. 1Тим  1Тимоф. 1Тимоф 1Тимофею 1Ti. 1Ti 1Tim. 1Tim 1Timothy',
    '2Тим': '2Тим. 2Тим 2Тимоф. 2Тимоф 2Тимофею 2Ti. 2Ti 2Tim. 2Tim 2Timothy',
    Тит: 'Тит. Тит Титу Tit. Tit Ti. Ti Titus',
    Флм: 'Флм. Флм Филимон. Филимон Филимону Phm. Phm Phile. Phile Phlm. Phlm Philemon',
    Евр: 'Евр. Евр Евреям He. He Heb. Heb Hebr. Hebr Hebrews',
    Иак: 'Иак. Иак Ик. Ик Иаков Иакова Jas. Jas Ja. Ja Jam. Jam Jms. Jms James',
    '1Пет': '1Пет. 1Пет 1Пт. 1Пт 1Птр. 1Птр 1Петр. 1Петр 1Петра 1Pe. 1Pe 1Pet. 1Pet 1Peter',
    '2Пет': '2Пет. 2Пет 2Пт. 2Пт 2Птр. 2Птр 2Петр. 2Петр 2Петра 2Pe. 2Pe 2Pet. 2Pet 2Peter',
    '1Ин': '1Иоан. 1Иоан 1Ин. 1Ин 1Иоанн 1Иоанна 1Jn. 1Jn 1Jo. 1Jo 1Joh. 1Joh 1Jno. 1Jno 1John',
    '2Ин': '2Иоан. 2Иоан 2Ин. 2Ин 2Иоанн 2Иоанна 2Jn. 2Jn 2Jo. 2Jo 2Joh. 2Joh 2Jno. 2Jno 2John',
    '3Ин': '3Иоан. 3Иоан 3Ин. 3Ин 3Иоанн 3Иоанна 3Jn. 3Jn 3Jo. 3Jo 3Joh. 3Joh 3Jno. 3Jno 3John',
    Иуд: 'Иуд. Иуд Ид. Ид Иуда Иуды Jud. Jud Jude Jd. Jd',
    Откр: 'Откр. Откр Отк. Отк Откровен. Откровен Апок. Апок Откровение Апокалипсис Rev. Rev Re. Re Rv. Rv Revelation',
};

const db = new sqlite3.Database('./ELZ.sqlite3');

const fetchAll = (query) => {
    return new Promise((resolve, reject) => {
        db.all(query, (err, rows) => {
            if (err) {
                reject(err);
            } else {
                resolve(rows);
            }
        });
    });
};

db.serialize(async function () {
    // db.run('CREATE TABLE lorem (info TEXT)');

    // var stmt = db.prepare('INSERT INTO lorem VALUES (?)');
    // for (var i = 0; i < 10; i++) {
    //     stmt.run('Ipsum ' + i);
    // }
    // stmt.finalize();

    const books = await fetchAll('SELECT short_name, long_name, book_number FROM books ORDER BY book_number ASC');
    const verses = await fetchAll('SELECT chapter, verse, text, book_number FROM verses ORDER BY chapter, verse ASC');

    const booksByNumber = books.reduce((acc, curr) => {
        acc[curr.book_number] = curr;
        return acc;
    }, {});

    const versesByBook = groupBy(verses, 'book_number');

    fs.mkdir('../bible/91Slavic', { recursive: true });

    let bibleqtContents = `
BibleName = Церковнославянский
BibleShortName = ELZ

Bible = Y
OldTestament = Y
NewTestament = Y
Apocrypha = N

Greek = N

HTMLFilter = <font </font>

Alphabet = АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя

DefaultEncoding = utf-8

ChapterSign = <h4>
VerseSign = <p>

BookQty = ${books.length}\n\n
    `;

    Object.keys(versesByBook).map(async (bookNumber) => {
        let fileContents = '';
        const versesByChapter = groupBy(versesByBook[bookNumber], 'chapter');
        Object.keys(versesByChapter).forEach((chapter) => {
            fileContents += `<h4>${chapter}</h4>\n`;
            const verses = versesByChapter[chapter];
            verses.forEach((verse) => {
                fileContents += `<p>${verse.verse} ${verse.text}</p>\n`;
            });
        });

        const bookInfo = booksByNumber[bookNumber];
        bibleqtContents += `
PathName = ${bookNumber}.htm
FullName = ${bookInfo.long_name}
ShortName = ${bookNames[bookInfo.short_name]}
ChapterQty = ${Object.keys(versesByChapter).length}
        `;

        await fs.writeFile(`../bible/91Slavic/${bookNumber}.htm`, fileContents);

        console.log(`../bible/91Slavic/${bookNumber}.htm written`);
    });

    await fs.writeFile('../bible/91Slavic/bibleqt.ini', bibleqtContents);
});
