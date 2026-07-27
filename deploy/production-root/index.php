<?php

/*
 * جسر جذر public_html → واجهة Laravel في public/index.php.
 *
 * يُستخدم فقط إذا كان جذر الدومين مثبّتًا على public_html ولا يمكن تحويله إلى
 * public/. مسارات public/index.php الداخلية (__DIR__.'/../') تُحلّ صحيحًا لأن
 * __DIR__ يبقى مجلد public/.
 *
 * الأفضل أمنيًا: حوّل جذر الدومين إلى public_html/public بدل استخدام هذا الملف.
 */
require __DIR__.'/public/index.php';
