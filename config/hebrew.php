<?php

return [
    /*
    | Editorial exceptions are deliberately small and reviewable. Keys are
    | normalized source spellings; values never replace the Hebrew itself.
    */
    'transliteration_overrides' => [
        'נפי' => 'Nefi',
        'למן' => 'Laman',
        'למואל' => 'Lemuel',
        'לחי' => 'Lehi',
        'ירושלם' => 'Yerushalem',
        'יהוה' => 'YHWH',
    ],

    'local_lemmas' => [
        'נפי' => ['hebrew' => 'נְפִי', 'transliteration' => 'Nefi', 'gloss' => 'Nephi'],
        'למן' => ['hebrew' => 'לָמָן', 'transliteration' => 'Laman', 'gloss' => 'Laman'],
        'למואל' => ['hebrew' => 'לְמוּאֵל', 'transliteration' => 'Lemuel', 'gloss' => 'Lemuel'],
        'לחי' => ['hebrew' => 'לְחִי', 'transliteration' => 'Lehi', 'gloss' => 'Lehi'],
        'סם' => ['hebrew' => 'סַם', 'transliteration' => 'Sam', 'gloss' => 'Sam'],
    ],

    // Used only after the source morphology endpoint has identified the same
    // Strong's entry. These repair missing/N/A dictionary fields; they do not
    // create a match by string similarity.
    'strongs_fallbacks' => [
        'H5612' => ['hebrew' => 'סֵפֶר', 'transliteration' => 'sefer', 'definition' => 'book, scroll', 'gloss' => 'book'],
        'H1419' => ['hebrew' => 'גָּדוֹל', 'transliteration' => 'gadol', 'definition' => 'great, large', 'gloss' => 'great'],
        'H7223' => ['hebrew' => 'רִאשׁוֹן', 'transliteration' => 'rishon', 'gloss' => 'first'],
        'H802' => ['hebrew' => 'אִשָּׁה', 'transliteration' => 'ishah', 'gloss' => 'woman'],
        'H3117' => ['hebrew' => 'יוֹם', 'transliteration' => 'yom', 'gloss' => 'day'],
    ],
];
