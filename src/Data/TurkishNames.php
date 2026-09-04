<?php

declare(strict_types=1);

namespace Redakte\Data;

/**
 * T.C. Nüfus ve Vatandaşlık İşleri Genel Müdürlüğü (NVİ) ve TÜİK
 * verilerine dayalı en yaygın Türkçe kadın, erkek ve uniseks adlar listesi.
 *
 * O(1) anlık hash kontrolü için tüm isimler küçük harfle anahtar olarak saklanır.
 */
final class TurkishNames
{
    /** @var array<string, true>|null */
    private static ?array $names = null;

    /**
     * @return array<string, true>
     */
    public static function all(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $list = [
            // A
            'abbas', 'abdullah', 'abdurrahman', 'açelya', 'adem', 'adil', 'adnan', 'afet', 'afra',
            'ahu', 'ahmet', 'ajda', 'akın', 'alara', 'aleyna', 'ali', 'alican', 'alim', 'alpaslan',
            'alper', 'alperen', 'alp', 'altan', 'amine', 'anıl', 'aras', 'arda', 'arif', 'armağan',
            'arzu', 'asena', 'asım', 'asil', 'asiye', 'aslan', 'aslı', 'aslıhan', 'asude', 'asya',
            'aşkın', 'atakan', 'atalay', 'atanur', 'ateş', 'atıf', 'atilla', 'attila', 'avni',
            'aybars', 'aybegüm', 'ayberk', 'aybike', 'aybige', 'aybüke', 'aycan', 'ayça', 'aydan',
            'aydeniz', 'aydın', 'ayfer', 'aygen', 'aygül', 'aygün', 'ayhan', 'aykut', 'ayla',
            'aylin', 'aynur', 'aysel', 'aysu', 'aysun', 'ayşe', 'ayşegül', 'ayşen', 'ayşenur',
            'aytaç', 'aytek', 'aytekin', 'ayten', 'azim', 'azime', 'aziz', 'azize', 'azra',

            // B
            'baha', 'bahadır', 'bahar', 'bahattin', 'bahri', 'bahriye', 'bahtiyar', 'baki', 'bakiye',
            'banu', 'baransel', 'barış', 'barkın', 'barlas', 'bartu', 'basri', 'başak', 'başar',
            'batıhan', 'batuhan', 'batur', 'baturay', 'baybars', 'bayram', 'bedir', 'bedirhan',
            'bedri', 'bedriye', 'begüm', 'behice', 'behiç', 'behlül', 'behram', 'behzat', 'bekir',
            'belgin', 'beliz', 'belkıs', 'benan', 'bener', 'bengi', 'bengisu', 'bengü', 'beren',
            'berfin', 'beria', 'beril', 'berin', 'berka', 'berkay', 'berke', 'berk', 'berker',
            'berra', 'berrak', 'berrin', 'besim', 'beste', 'betül', 'beyhan', 'beyza', 'beyzanur',
            'bilal', 'bilge', 'bilgehan', 'bilgin', 'bilnur', 'bilsen', 'birce', 'bircan', 'birgül',
            'birkan', 'birsel', 'birsen', 'bora', 'boran', 'buğra', 'buket', 'bulut', 'burak',
            'burcu', 'burçin', 'burhan', 'burhanettin', 'bülent', 'büşra',

            // C - Ç
            'cabbar', 'cafer', 'cahid', 'cahit', 'cahide', 'can', 'canan', 'candan', 'canel',
            'caner', 'cankan', 'cankut', 'cansu', 'cavit', 'celal', 'celalettin', 'celil', 'cem',
            'cemal', 'cemalettin', 'cemil', 'cemile', 'cemre', 'cengiz', 'cengizhan', 'cenk',
            'cenker', 'ceren', 'cevahir', 'cevat', 'cevdet', 'ceyda', 'ceyhun', 'ceylan', 'cihan',
            'cihangir', 'coşkun', 'cuma', 'cumhur', 'cüneyt', 'çağan', 'çağdaş', 'çağıl', 'çağın',
            'çağla', 'çağlar', 'çağlayan', 'çağrı', 'çan', 'çelebi', 'çetin', 'çiçek', 'çiğdem',
            'çiler', 'çınar', 'çise', 'çisem',

            // D
            'damla', 'danyal', 'davut', 'defne', 'deha', 'demir', 'demircan', 'demirhan', 'demet',
            'deniz', 'derya', 'destan', 'devran', 'devrim', 'dicle', 'didem', 'dilara', 'dilaver',
            'dilay', 'dilek', 'dilruba', 'dinçer', 'diren', 'direnç', 'doğa', 'doğaç', 'doğan',
            'doğancan', 'doğukan', 'doğuş', 'dolunay', 'dora', 'doruk', 'dudu', 'duran', 'durdu',
            'durmuş', 'dursun', 'duygu', 'dündar', 'dürdane', 'dürriye',

            // E
            'ebru', 'ece', 'ecevit', 'ecmel', 'ecem', 'ecren', 'eda', 'edanur', 'edip', 'ediz',
            'efe', 'efecan', 'eftal', 'ege', 'egemen', 'ejder', 'ekrem', 'ela', 'elçin', 'elif',
            'elmas', 'elvan', 'emel', 'emin', 'emine', 'emir', 'emirhan', 'emrah', 'emre', 'ender',
            'enes', 'engin', 'enis', 'enise', 'ensar', 'enver', 'eray', 'erberk', 'ercan', 'ercüment',
            'erdal', 'erdem', 'erden', 'erdoğan', 'eren', 'ergün', 'erhan', 'erkan', 'erkin', 'erman',
            'erol', 'ersan', 'ersel', 'ersin', 'ertan', 'ertuğrul', 'esad', 'esat', 'eser', 'esin',
            'esma', 'esmanur', 'esmeray', 'esra', 'eşref', 'etem', 'ethem', 'evren', 'evrim', 'eylem',
            'eymen', 'eyüp', 'ezel', 'ezgi',

            // F
            'fadime', 'fahrettin', 'fahri', 'fahriye', 'faik', 'faruk', 'fatih', 'fatma', 'fatmanur',
            'fatoş', 'fazıl', 'fazilet', 'fehmi', 'fehim', 'feramuz', 'feraye', 'ferda', 'ferdi',
            'ferhan', 'ferhat', 'feridun', 'feride', 'feriha', 'ferit', 'ferman', 'feryal', 'feyza',
            'feyzullah', 'fidan', 'figen', 'fikret', 'fikri', 'fikriye', 'filiz', 'firdevs', 'fuat',
            'fulya', 'funda', 'furkan', 'füsun',

            // G
            'gaffar', 'galip', 'gamze', 'gaye', 'gazanfer', 'gazi', 'genco', 'gizem', 'gonca',
            'gökberk', 'gökcan', 'gökçe', 'gökçen', 'gökhan', 'gökmen', 'göknur', 'göksel', 'göksu',
            'görkem', 'gözde', 'gül', 'gülay', 'gülbahar', 'gülben', 'gülcan', 'gülçin', 'gülden',
            'güler', 'gülfem', 'gülgün', 'gülhan', 'gülizar', 'güllü', 'gülperi', 'gülriz', 'gülsüm',
            'gülşen', 'gültekin', 'gülten', 'günay', 'günce', 'gündoğdu', 'gündüz', 'güner', 'güneş',
            'güngör', 'güniz', 'güntaç', 'güray', 'gürbüz', 'gürcan', 'gürel', 'gürkan', 'gürol',
            'gürsel', 'güven', 'güvenç', 'güzide', 'güzin',

            // H
            'habib', 'habibe', 'hacer', 'hadi', 'hafize', 'hakan', 'hakkı', 'haldun', 'hale',
            'halide', 'halil', 'halim', 'halime', 'halis', 'halit', 'haluk', 'hamdi', 'hamdiye',
            'hamit', 'hamza', 'handan', 'hande', 'harun', 'hasan', 'hasbi', 'hasret', 'haşim',
            'hatice', 'hayal', 'hayati', 'haydar', 'hayrettin', 'hayri', 'hayriye', 'hayrunnisa',
            'hazal', 'hazar', 'hazine', 'hazım', 'hediye', 'helin', 'hıfzı', 'hidayet', 'hikmet',
            'hilal', 'hilmi', 'huriye', 'hurşit', 'hülya', 'hümeyra', 'hüsamettin', 'hüseyin',
            'hüsne', 'hüsniye', 'hüsnü',

            // I - İ
            'ılgaz', 'ılgın', 'ışık', 'ışıl', 'ışılay', 'ışın', 'ibrahim', 'iclal', 'idris',
            'iffet', 'ihsan', 'ikbal', 'ilhami', 'ilhan', 'ilkay', 'ilke', 'ilker', 'ilknur',
            'ilksen', 'ilyas', 'imdat', 'imge', 'imran', 'inanç', 'inci', 'incilay', 'ipek',
            'irem', 'irfan', 'isa', 'ishak', 'iskender', 'islam', 'ismail', 'ismet', 'israfil', 'izzet',

            // K
            'kaan', 'kadir', 'kadri', 'kadriye', 'kahraman', 'kamil', 'kamile', 'kamuran', 'kasım',
            'kaya', 'kayahan', 'kayra', 'kazım', 'kemal', 'kemalettin', 'kenan', 'kerem', 'kerim',
            'keriman', 'kezban', 'kıvanç', 'koray', 'korcan', 'korkut', 'köksal', 'kubilay',
            'kudret', 'kunt', 'kutay', 'kuzey', 'kübra', 'kürşat',

            // L
            'lale', 'latif', 'latife', 'leman', 'levent', 'leyla', 'lokman', 'lütfi', 'lütfiye',

            // M
            'macit', 'mahir', 'mahmut', 'maide', 'makbule', 'mansur', 'mazhar', 'mediha', 'medine',
            'mehdi', 'mehmet', 'mehtap', 'melahat', 'melek', 'melih', 'meliha', 'melike', 'melis',
            'melisa', 'meltem', 'memduh', 'menderes', 'menekşe', 'mengü', 'meriç', 'merih', 'mert',
            'mertcan', 'merve', 'meryem', 'mesut', 'mete', 'metehan', 'metin', 'mevlüt', 'mithat',
            'mihriban', 'mine', 'miraç', 'miray', 'mirkan', 'mirsad', 'mualla', 'muammer', 'mucip',
            'mücahit', 'muhammet', 'muhammed', 'muharrem', 'muhsin', 'muhtar', 'muhteşem', 'mukaddes',
            'mukadder', 'murat', 'musa', 'mustafa', 'mutlu', 'mübeccel', 'müfit', 'müge', 'müjdat',
            'müjde', 'müjgan', 'mükerrem', 'mükremin', 'mümtaz', 'münir', 'münire', 'mürsel',
            'mürüvvet', 'müslüm', 'müşerref', 'müyesser',

            // N
            'naci', 'naciye', 'nadide', 'nadir', 'nadire', 'nafiz', 'nahit', 'naide', 'nail',
            'naime', 'nalan', 'namık', 'naz', 'nazan', 'nazım', 'nazife', 'nazik', 'nazire',
            'nazlı', 'nazmi', 'nazmiye', 'nebahat', 'nebi', 'necat', 'necati', 'necdet', 'necip',
            'necmettin', 'nedim', 'nedret', 'nehir', 'nejla', 'nejat', 'nergis', 'nermin', 'nesim',
            'neslihan', 'nesrin', 'neşat', 'neşe', 'neşet', 'nevin', 'nevra', 'nevzat', 'neyzen',
            'nezahat', 'nezaket', 'nezih', 'nezihe', 'nida', 'nigar', 'nihal', 'nihan', 'nihat',
            'nil', 'nilay', 'nilgün', 'nilüfer', 'nimet', 'nisa', 'niyazi', 'nuh', 'numan', 'nur',
            'nuran', 'nuray', 'nurcan', 'nurdan', 'nurgül', 'nurhan', 'nuri', 'nuriye', 'nurşah',
            'nurten', 'nüvit',

            // O - Ö
            'oben', 'ogan', 'ogün', 'oğulcan', 'oğuz', 'oğuzhan', 'okan', 'oktay', 'olcay', 'olgun',
            'oltan', 'onur', 'onurcan', 'orhan', 'orhun', 'orkun', 'oruç', 'osman', 'oya', 'ozan',
            'öcal', 'ögeday', 'ömer', 'ömür', 'önder', 'öner', 'özay', 'özcan', 'özden', 'özdil',
            'özen', 'özenç', 'özer', 'özge', 'özgen', 'özgül', 'özgün', 'özgür', 'özkan', 'özlem',
            'özlen', 'öznur', 'özten', 'öztürk',

            // P
            'pakize', 'pamir', 'parla', 'pars', 'paşa', 'payidar', 'pelin', 'pelinsu', 'perihan',
            'pertev', 'pervin', 'petek', 'peyami', 'pınar', 'pırıl', 'polat', 'poyraz',

            // R
            'rabia', 'rafet', 'ragıp', 'rahime', 'rahmi', 'raif', 'rakım', 'ramazan', 'rana',
            'rasim', 'raşit', 'rauf', 'recai', 'recep', 'refik', 'refika', 'reha', 'remzi',
            'remziye', 'renan', 'resul', 'reşat', 'reşit', 'rıdvan', 'rıfat', 'rıfkı', 'rıza',
            'rojda', 'roni', 'roza', 'ruhi', 'ruhsar', 'rukiye', 'rüçhan', 'rüstem', 'rüştü', 'rüya',

            // S - Ş
            'saadettin', 'saadet', 'sabahat', 'sabahattin', 'sabit', 'sabri', 'sabriye', 'sacide',
            'sacit', 'sadberk', 'sadık', 'sadri', 'sadullah', 'saffet', 'safi', 'safiye', 'sait',
            'saide', 'saim', 'saime', 'sakıp', 'saliha', 'salih', 'salim', 'samet', 'sami',
            'samiha', 'samim', 'saner', 'sarp', 'sarper', 'savaş', 'seçil', 'seçkin', 'seda',
            'sedat', 'sedef', 'sefer', 'seha', 'seher', 'selami', 'selcan', 'selcen', 'selçuk',
            'selda', 'selen', 'selim', 'selime', 'selin', 'selman', 'selvi', 'sema', 'semahat',
            'semih', 'semiha', 'semra', 'sena', 'senai', 'sencer', 'serap', 'seray', 'sercan',
            'serdar', 'seren', 'sergen', 'serhan', 'serhat', 'serkan', 'serpil', 'serra', 'sertan',
            'servet', 'sevcan', 'sevda', 'sevgi', 'sevil', 'sevilay', 'sevim', 'sevinç', 'seyfi',
            'seyfullah', 'seyhan', 'seyit', 'sezen', 'sezer', 'sezgin', 'sıddık', 'sıdıka', 'sıla',
            'sırma', 'sibel', 'simge', 'sinan', 'sinem', 'siret', 'su', 'suat', 'subhi', 'sude',
            'sudenur', 'sultan', 'sumru', 'suna', 'sunay', 'suzan', 'süheyl', 'süheyla', 'süleyman',
            'sümeyye', 'şaban', 'şadi', 'şadiye', 'şafak', 'şahap', 'şahin', 'şahika', 'şahsenem',
            'şakir', 'şakire', 'şaziye', 'şebnem', 'şefik', 'şefika', 'şehnaz', 'şehriban',
            'şenay', 'şener', 'şenol', 'şerafettin', 'şeref', 'şerif', 'şerife', 'şermin', 'şevket',
            'şevki', 'şevval', 'şeyda', 'şeyma', 'şinasi', 'şirin', 'şule', 'şükran', 'şükrü', 'şükriye',

            // T
            'tacettin', 'taha', 'tahir', 'tahsin', 'talat', 'talha', 'talip', 'tamer', 'tan',
            'taner', 'tangör', 'tansel', 'tansu', 'tarık', 'tarkan', 'taşkın', 'tayfun', 'tayfur',
            'taylan', 'tayyar', 'tekin', 'temel', 'teoman', 'tevfik', 'tevhide', 'tijen', 'timur',
            'tolga', 'tolgahan', 'toygar', 'tuana', 'tuba', 'tuğba', 'tuğberk', 'tuğçe', 'tuğrul',
            'tuna', 'tuncay', 'tuncer', 'turan', 'turgay', 'turgut', 'tülay', 'tülin', 'türkan',
            'türker', 'tutku',

            // U - Ü
            'ufuk', 'uğur', 'uğurcan', 'ulaş', 'ulvi', 'ulviye', 'umur', 'umut', 'umutcan', 'ural',
            'uras', 'utku', 'uygar', 'uygur', 'uzay', 'ülkü', 'ümit', 'ümran', 'ünal', 'üner',
            'ünsal', 'ünzile', 'üstün',

            // V
            'vahap', 'vahdet', 'vahid', 'vahit', 'vacit', 'vakkas', 'vecihi', 'vedat', 'vefa',
            'veli', 'veysel', 'volkan', 'vural',

            // Y
            'yadigar', 'yağmur', 'yağız', 'yahya', 'yakup', 'yalçın', 'yalın', 'yaman', 'yaren',
            'yasin', 'yaşar', 'yavuz', 'yeliz', 'yeşim', 'yiğit', 'yiğitcan', 'yılmaz', 'yonca',
            'yunus', 'yusuf', 'yücel', 'yüksel',

            // Z
            'zafer', 'zahit', 'zahide', 'zehra', 'zekai', 'zekeriya', 'zeki', 'zekiye', 'zeliha',
            'zerrin', 'zeynel', 'zeynep', 'ziya', 'ziver', 'zöhre', 'zuhal', 'zübeyde', 'zübeyir',
            'zühal', 'zülfikar', 'zülfü', 'zümrüt',
        ];

        $map = [];
        foreach ($list as $name) {
            $map[$name] = true;
        }

        self::$names = $map;

        return self::$names;
    }

    /**
     * Verilen kelimenin bilinen bir Türkçe ad olup olmadığını O(1) hızla denetler
     */
    public static function isFirstName(string $word): bool
    {
        $normalized = mb_strtolower(trim($word), 'UTF-8');
        return isset(self::all()[$normalized]);
    }
}
