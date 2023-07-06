const cheerio = require('cheerio');
const fs = require('fs').promises;

const fetch = (...args) => import('node-fetch').then(({ default: fetch }) => fetch(...args));

const run = async () => {
    for (let book = 1; book <= 66; book++) {
        const firstLink = `https://www.wordproject.org/bibles/am/${String(book).padStart(2, '0')}/1.htm`
        const html = await (await fetch(firstLink)).text()

        const $ = cheerio.load(html)

        const chapterLinks = [firstLink, ...$('.textHeader .ym-noprint .chap').toArray().map((el) => `https://www.wordproject.org/bibles/am/${String(book).padStart(2, '0')}/${el.attribs.href}`)]
        let bookText = ''
        for await (const chapterLink of chapterLinks) {
            const html = await (await fetch(chapterLink)).text()
            const $ = cheerio.load(html)
            $('.dimver').remove()
            $.root()
                .contents()
                .filter(function () {
                    return this.type === 'comment';
                })
                .remove();
            const body = $('.textBody')
                .toString()
                .replace('<!--span class="verse" id="1">1  </span-->', '<br><span class="verse" id="1">1 </span>')
                .replace('<!--... the Word of God:-->', '')
                .replace('<!--... sharper than any twoedged sword... -->', '')
                .replaceAll('<p>', '')
                .replaceAll('</p>', '')
                .replaceAll('<div class="textBody" id="textBody">', '')
                .replaceAll('</div>', '')
                .replaceAll('<h3>', '<h4>')
                .replaceAll('</h3>', '</h4>')
                .replace(/<br><span class="verse" id="\d+">(\d+) <\/span>/g, '<p>$1 ');
            bookText += body
        }
        await fs.writeFile(`${book}.htm`, bookText)
    }
};

run();
