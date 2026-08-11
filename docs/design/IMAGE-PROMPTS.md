# برومبتات صور الموقع — النسخة النهائية

> تُولَّد الصور وتُوضع في `C:\Users\lenovo\Downloads\صور الموقع` بالأسماء المكتوبة،
> ثم تُعالَج (فصل خلفية · ضغط WebP · تلاشٍ عند الحاجة) وتُركَّب في مواضعها.
> العائلة البصرية مقفلة في [`STYLESEED.md`](../../STYLESEED.md).

---

## قواعد تسري على كل صورة — بلا استثناء

هذه القواعد ليست تفضيلات، بل شروط تقنية جُرِّبت وسقط ما خالفها:

| القاعدة | لماذا |
|---|---|
| **خلفية بيضاء نقيّة `#FFFFFF`** مسطّحة، بلا تدرّج ولا هالة ولا ظلّ عليها | صورة أولى جاءت بخلفية داكنة والجسم داكن: وسيط الإضاءة **0.33 من 255** و٩٢٪ من البكسلات تحت ٤٠ — لا عتبة تفصلهما، فتعذّر القصّ نهائيًّا |
| **الإضاءة من اليسار** | الشعاع الطيفي يسار اللوحة؛ إضاءة من اليمين تنقض التكوين كله |
| **لا إضاءة خلفية (`no backlight`, `no rim light from behind`)** | تعطي أثرًا جميلًا وتلغي إمكان الفصل عن الخلفية |
| **صفر نصّ وصفر شعارات** داخل الصورة | كل النصّ من القالب، وإلا لم يترجم ولم يُقرأ آليًّا |
| الضلع الأطول **٢٤٠٠px** فأكثر | تُصغَّر عندي إلى ما يلزم؛ التكبير لا يعوَّض |
| إطار **طولي** حين يجب أن يخرج الحزام من الإطار | المولّد يميل إلى المربّع فيقصّ الحزام ويسقط القُطر |

**العائلة البصرية المقفلة:** فولاذ مصقول بارد · تاج وزرّ **برتقاليان** · شريط مؤشر **أخضر ليموني `#9EF020`** · جلد **أبيض** بخياطة ظاهرة · إضاءة استوديو ناعمة من اليسار.

> الأخضر ليس ذوقًا: هو لون `measured` في تدرّج الدليل، والأصفر لون `inferred`.
> عكسهما يقلب المعنى. النسخة الأولى جاءت صفراء وصحّحتُ ٢٬٥٤٨ بكسل بإزاحة لونية.

---

## أ · `report-open.png` — التقرير المطبوع مفتوحًا
**الموضع:** قسم «نموذج النتيجة» — الجسم البطل فيه.

```
Ultra-realistic product photograph of a premium printed report booklet lying
open flat, seen from directly above. Thick uncoated off-white paper with
visible fibre texture and a crisp centre fold. The left page shows a large
circular gauge diagram and a short stack of horizontal bars of decreasing
length; the right page shows three ruled rows and a small three-colour key
of green, blue and amber squares along the bottom edge. All printed content
is abstract shapes, ruled lines and blocks — absolutely no readable letters
or words. Subtle letterpress deboss so the printing catches a little relief.
A second closed booklet peeks from underneath at a slight angle.
Soft studio light from the LEFT, delicate contact shadow under the paper.
Photorealistic, 8k, macro paper detail, pure solid white background.
```
**تجنّب:** `no readable text, no letters, no words, no logo, no glossy paper, no background scene, no vignette, no coloured lighting`

---

## ب · `device-exploded.png` — الجهاز مفكّكًا
**الموضع:** قسم «المحاور الثمانية» — كل طبقة تقابل محورًا.

```
Ultra-realistic exploded technical view of a futuristic precision measurement
instrument, its parts separated vertically along a single axis with even
spacing, floating as in an engineering assembly diagram. From top to bottom:
the brushed stainless-steel top bezel with four micro hex screws, the
circular pearl-white gauge dial with engraved ticks, a thin matte black
needle and a bright orange needle, an internal movement plate, a vertical
strip of five BRIGHT LIME GREEN (#9EF020) LED bar segments, the knurled
ORANGE crown, the main case body with fine vent slots and a dotted grille,
and the WHITE leather strap.
Straight-on frontal view, no perspective distortion, parts perfectly aligned
on the vertical axis. Soft studio light from the LEFT, gentle specular
highlights on brushed metal. Photorealistic, 8k, tall vertical frame,
pure solid white background.
```
**تجنّب:** `no text, no labels, no arrows, no numbers, no logo, no perspective tilt, no yellow LEDs, no background scene, no shadow on background`

---

## ج · `device-front.png` — الجهاز مواجهًا
**الموضع:** قسم «المنهجية» — يقف وحده وسط الخطوات الأربع.

```
Ultra-realistic 3D product render of the same futuristic precision
measurement instrument, photographed perfectly FLAT-ON, the square case and
the circular gauge face exactly parallel to the camera with no tilt and no
perspective distortion. Brushed stainless-steel case with heavily rounded
corners, four micro hex screws, fine vertical vent slots on the lower left,
dotted grille on the lower right. Pearl-white dial with engraved ticks, one
thin matte black needle and one bright orange needle. Knurled ORANGE crown
on the LEFT side, small orange button on the RIGHT. Five BRIGHT LIME GREEN
(#9EF020) LED bar segments on the upper right, top three lit.
The WHITE leather strap curves softly downward on both sides and exits the
bottom of the frame. Soft studio light from the LEFT.
Photorealistic, 8k, pure solid white background.
```
**تجنّب:** `no tilt, no angle, no perspective, no wrist, no hand, no text, no logo, no yellow LEDs, no background scene`

---

## د · `device-wrist.png` — الجهاز على المعصم
**الموضع:** القسم الختامي «ابدأ تشخيص مشروعك الآن».

```
Ultra-realistic photograph of the same brushed steel precision instrument
worn on a man's wrist, the arm extended horizontally across the frame, hand
relaxed and slightly open. Dark charcoal suit sleeve and a white shirt cuff
just visible at the edge of the frame. The white leather strap is fastened
neatly. The gauge face is clearly readable, the ORANGE crown and the LIME
GREEN LED strip visible.
Warm olive-tan skin. Strong directional light from the LEFT sculpting the
forearm, deep but detailed shadow on the far side.
Photorealistic, 8k, horizontal frame, pure solid white background.
```
**تجنّب:** `no face, no head, no background scene, no backlight, no rim light, no vignette, no text, no logo, no watch brand marks`

---

## هـ · `person-ecommerce.png` — صاحب متجر إلكتروني
**الموضع:** قسم المشكلات · صفحة قطاع التجارة الإلكترونية.

```
Dramatic side-lit studio photograph of a man in his early thirties of Middle
Eastern appearance with warm olive-tan skin, wearing a dark navy knitted
polo and a plain white t-shirt underneath — smart-casual, not a formal suit.
Seen from the side in profile facing RIGHT, cropped from the mid-chest up.
Short dark hair, neat trimmed beard, focused thoughtful expression, chin
level.
Lighting: strong directional key light from the LEFT sculpting the face and
shoulder, deep shadow on the far side, high contrast and moody — but the
subject stays clearly separated from the background.
Background: PURE SOLID WHITE (#FFFFFF), completely flat, evenly lit, no
gradient, no vignette, no glow, no shadow falling on the background.
8k, editorial studio photography, sharp focus on the profile edge.
```
**تجنّب:** `no black background, no dark background, no backlight, no rim light from behind, no glow, no vignette, no props, no logo, no text, no direct eye contact with camera`

---

## و · `person-education.png` — مسوّقة قطاع التعليم
**الموضع:** قسم المشكلات · صفحة قطاع التعليم.

```
Dramatic side-lit studio photograph of a woman in her early thirties of
Middle Eastern appearance with warm olive-tan skin, wearing a tailored beige
blazer over a cream blouse and a softly draped neutral-toned hijab. Seen
from the side in profile facing RIGHT, cropped from the mid-chest up. Calm
composed expression, chin slightly raised.
Lighting: strong directional key light from the LEFT sculpting the face,
deep shadow on the far side, high contrast and moody — but the subject stays
clearly separated from the background.
Background: PURE SOLID WHITE (#FFFFFF), completely flat, evenly lit, no
gradient, no vignette, no glow, no shadow falling on the background.
8k, editorial studio photography, sharp focus on the profile edge.
```
**تجنّب:** نفس قائمة «هـ».

---

## ز · `person-realestate.png` — وسيط عقاري
**الموضع:** قسم المشكلات · صفحة قطاع العقارات.

```
Dramatic side-lit studio photograph of a man in his forties of Middle
Eastern appearance with warm olive-tan skin, wearing a charcoal grey suit
with an open-collar white shirt and no tie. Seen from the side in profile
facing RIGHT, cropped from the mid-chest up. Short greying hair at the
temples, clean-shaven, confident measured expression.
Lighting: strong directional key light from the LEFT sculpting the face and
shoulder, deep shadow on the far side, high contrast and moody — but the
subject stays clearly separated from the background.
Background: PURE SOLID WHITE (#FFFFFF), completely flat, evenly lit, no
gradient, no vignette, no glow, no shadow falling on the background.
8k, editorial studio photography, sharp focus on the profile edge.
```
**تجنّب:** نفس قائمة «هـ».

---

## ما لا يُولَّد بالذكاء الاصطناعي

| العنصر | لماذا | البديل |
|---|---|---|
| صورة خالد في صفحة السيرة | شخص حقيقي — لا تُختلق صورته | صورة فوتوغرافية حقيقية بنفس الإضاءة: مفتاح من اليسار، خلفية بيضاء مسطّحة |
| أيقونات المحاور الثمانية | نظام أيقونات يحتاج اتساقًا هندسيًّا لا يعطيه مولّد الصور | أرسمها متّجهة (SVG) من مفردات المحاور نفسها |
| لقطات الشاشة والتقارير الحيّة | بيانات حقيقية لا رسومات | تُبنى من المنصة نفسها |

---

## بعد التوليد

ضع الملفات بأسمائها في المجلد، وقل «الصور جاهزة». المعالجة عندي:

1. فصل الخلفية البيضاء بالفيضان من الحواف مع **ملء الثقوب** — الياقة البيضاء ثقب داخلي يصير شفافًا بدونه
2. تآكل ٢–٣ بكسل قبل التنعيم فلا تبقى هالة بيضاء حول الشعر
3. قلب أفقي عند الحاجة ليواجه اتجاه القراءة
4. تلاشٍ مدموج في الصورة عند الحافة الملامسة للشعاع
5. ضغط WebP — الأصول الحالية نزلت من ٥٫٧ ميجابايت إلى ٢٨٤ كيلوبايت
6. تركيب في الموضع، ثم تصيير بـheadless Chrome وفحص التباين والمقاس وأهداف اللمس قبل التسليم
