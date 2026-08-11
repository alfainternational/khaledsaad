/*
 * ترجمة نصوص الواجهة داخل JavaScript.
 *
 * لماذا لا تُترجَم الحزمة نفسها: Vite يبني ملفًّا واحدًا يُخدَم لكل اللغات
 * ويُخزَّن في كاش المتصفح ببصمة محتواه. بناء حزمة لكل لغة يضاعف المخرَج
 * ويُبطل الكاش عند كل تبديل لغة. فالحزمة تبقى واحدة، والقاموس يصل من
 * القالب في `window.__I18N__` — وهو مخبوز مثل بقية الترجمة، لا يُولَّد
 * وقت الطلب.
 *
 * المفتاح هو النصّ العربي نفسه، كما في Blade وPHP: مفتاح مفقود يُرجع نفسه،
 * فاللغة الأم تعمل بلا قاموس إطلاقًا ولا تنكسر شاشة أبدًا.
 */

const dictionary = () => (typeof window !== 'undefined' && window.__I18N__) || {};

/**
 * @param {string} key نصّ عربي — هو المفتاح
 * @param {Object<string, string|number>} [replacements] نوّاب بصيغة :name
 * @returns {string}
 */
export function t(key, replacements) {
    let text = dictionary()[key] || key;

    if (replacements) {
        /*
         * الترتيب من الأطول إلى الأقصر: `:count` و`:countdown` في جملة
         * واحدة يجعل استبدال الأقصر أولًا يأكل بداية الأطول.
         */
        for (const name of Object.keys(replacements).sort((a, b) => b.length - a.length)) {
            text = text.split(':' + name).join(String(replacements[name]));
        }
    }

    return text;
}

export default t;
