<?php
/** African American History — the people on the page, plus the banner music.
 *
 *  The page used to be authored entirely in aahistory.php. Everyone on it now
 *  lives in a table so each name can have its own page that William writes.
 *  The original list is planted ONCE (see aah_seed) and never re-planted, so a
 *  name he removes stays removed. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function aah_migrate() {
    static $done = false;
    if ($done) return; $done = true;

    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    db()->exec("CREATE TABLE IF NOT EXISTS aah_people (
      id $AI, slug VARCHAR(60) NOT NULL, name VARCHAR(160) NOT NULL, role VARCHAR(220) DEFAULT '',
      category VARCHAR(30) NOT NULL DEFAULT 'trailblazers', photo VARCHAR(255) DEFAULT '',
      born VARCHAR(60) DEFAULT '', body TEXT, sort INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'published', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE UNIQUE INDEX idx_aahp_slug ON aah_people(slug)"); } catch (\Throwable $e) {}

    db()->exec("CREATE TABLE IF NOT EXISTS aah_meta (
      k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT
    )$ENG");

    aah_seed();
}

/* ---------------- settings ---------------- */
function aah_meta($k, $default = '') {
    try { $r = one("SELECT v FROM aah_meta WHERE k=?", [$k]); }
    catch (\Throwable $e) { return $default; }
    return $r ? (string)$r['v'] : $default;
}
function aah_meta_set($k, $v) {
    try {
        if (one("SELECT k FROM aah_meta WHERE k=?", [$k])) q("UPDATE aah_meta SET v=? WHERE k=?", [(string)$v, $k]);
        else q("INSERT INTO aah_meta (k,v) VALUES (?,?)", [$k, (string)$v]);
    } catch (\Throwable $e) {}
}

/** The banner track, or an empty array when none has been uploaded. */
function aah_music() {
    $file = trim(aah_meta('music_file', ''));
    if ($file === '' || !is_file(dirname(__DIR__) . '/public/' . $file)) return [];
    return [
        'file'  => $file,
        'title' => aah_meta('music_title', ''),
        'auto'  => aah_meta('music_auto', '1') !== '0',
    ];
}

/** Save an uploaded track -> assets/aahistory/music/. Returns [relPath, error]. */
function aah_store_music($field = 'track') {
    $rel = 'assets/aahistory/music';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return ['', ''];
    if ($_FILES[$field]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$field]['error'] === UPLOAD_ERR_FORM_SIZE)
        return ['', 'That file is bigger than the server allows — please use a track under 20 MB.'];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return ['', 'The music could not be uploaded — please try again.'];
    if ($_FILES[$field]['size'] > 20 * 1024 * 1024) return ['', 'That track is larger than 20 MB — please use a shorter or smaller file.'];

    // Trust the extension only after the bytes look like audio/video we can serve.
    $name = strtolower($_FILES[$field]['name']);
    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $ok   = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'];
    if (!isset($ok[$ext])) return ['', 'Please upload an MP3 (or M4A, OGG, WAV). Other kinds of file will not play in a browser.'];
    $head = @file_get_contents($_FILES[$field]['tmp_name'], false, null, 0, 12);
    if ($head === false || strlen($head) < 4) return ['', 'That file looks empty.'];
    $looksAudio = (substr($head, 0, 3) === 'ID3')                       // mp3 with tags
        || (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0)  // raw mp3 frame
        || (substr($head, 4, 4) === 'ftyp')                             // m4a / mp4
        || (substr($head, 0, 4) === 'OggS')                             // ogg
        || (substr($head, 0, 4) === 'RIFF');                            // wav
    if (!$looksAudio) return ['', 'That does not look like an audio file — please upload the MP3 itself, not a link or a document.'];

    $fname  = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $absDir . '/' . $fname)) return ['', 'Sorry — the music could not be saved.'];
    return [$rel . '/' . $fname, ''];
}

/** Delete the current track's file as well as the setting, so nothing is orphaned. */
function aah_remove_music() {
    $file = trim(aah_meta('music_file', ''));
    if ($file !== '' && strpos($file, 'assets/aahistory/music/') === 0) {
        $abs = dirname(__DIR__) . '/public/' . $file;
        if (is_file($abs)) @unlink($abs);
    }
    aah_meta_set('music_file', '');
    aah_meta_set('music_title', '');
}

/* ---------------- people ---------------- */
function aah_people($category = '', $all = false) {
    try {
        $w = []; $p = [];
        if (!$all) $w[] = "status='published'";
        if ($category !== '') { $w[] = "category=?"; $p[] = $category; }
        $sql = "SELECT * FROM aah_people" . ($w ? " WHERE " . implode(' AND ', $w) : '') . " ORDER BY sort, id";
        return all($sql, $p);
    } catch (\Throwable $e) { return []; }
}
function aah_person($slug) {
    try { return one("SELECT * FROM aah_people WHERE slug=?", [(string)$slug]); }
    catch (\Throwable $e) { return null; }
}
function aah_person_by_id($id) {
    try { return one("SELECT * FROM aah_people WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}
function aah_next_sort($category) {
    $r = one("SELECT MAX(sort) m FROM aah_people WHERE category=?", [$category]);
    return ($r && $r['m'] !== null) ? ((int)$r['m'] + 1) : 0;
}
function aah_delete_person($id) { q("DELETE FROM aah_people WHERE id=?", [(int)$id]); }

/** A URL-safe, unique slug for a name. */
function aah_slug($name, $ignoreId = 0) {
    $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', html_entity_decode($name, ENT_QUOTES, 'UTF-8')), '-'));
    if ($s === '') $s = 'person';
    $s = substr($s, 0, 50);
    $try = $s; $n = 2;
    while (true) {
        $row = one("SELECT id FROM aah_people WHERE slug=?", [$try]);
        if (!$row || (int)$row['id'] === (int)$ignoreId) return $try;
        $try = $s . '-' . $n++;
    }
}

/** Save a portrait -> assets/aahistory/people/. Returns [relPath, error]. */
function aah_store_photo($field = 'photo') {
    $rel = 'assets/aahistory/people';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return ['', ''];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return ['', 'The picture could not be uploaded — please try again.'];
    $info = @getimagesize($_FILES[$field]['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($allowed[$info['mime']])) return ['', 'That file is not a picture (JPG, PNG, GIF or WEBP only).'];
    if ($_FILES[$field]['size'] > 12 * 1024 * 1024) return ['', 'That picture is larger than 12 MB — please pick a smaller one.'];
    $fname  = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $allowed[$info['mime']];
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $absDir . '/' . $fname)) return ['', 'Sorry — the picture could not be saved.'];
    return [$rel . '/' . $fname, ''];
}

/** Two initials for someone with no portrait. */
function aah_mono_name($name) {
    $parts = array_values(array_filter(preg_split('/\s+/', trim(strip_tags($name)))));
    if (!$parts) return '&#10022;';
    return e(strtoupper(substr($parts[0],0,1) . (count($parts) > 1 ? substr(end($parts),0,1) : '')));
}

/** Categories, in the order they appear on the page. */
function aah_categories() {
    return [
        'trailblazers' => 'Trailblazers',
        'inventions'   => 'Inventions & Innovations',
        'politics'     => 'Politics & Leadership',
        'science'      => 'Science & Medicine',
        'sports'       => 'Sports',
    ];
}
function aah_category_label($k) { $c = aah_categories(); return $c[$k] ?? 'Trailblazers'; }
/** Where a category sends you back to on the history page. */
function aah_category_anchor($k) { return 'aahistory.php#' . ($k === 'trailblazers' ? 'trailblazers' : $k); }

/** Plant the original page content — ONCE, ever.
 *
 *  Deliberately keyed on a flag rather than "is the table empty". A seed that
 *  fires whenever a table is empty quietly resurrects everything the owner
 *  deleted, which is exactly what happened to the Enterprise videos. */
function aah_seed() {
    if (aah_meta('seeded', '') === '1') return;

    /* Each one starts with a short account from public history so no page is
       ever blank. William edits or replaces any of it from the page itself. */
    $rows = [
        // [slug, name, role, category, years, story]
        ['douglass', 'Frederick Douglass', 'Abolitionist & Author', 'trailblazers', 'c. 1818 – 1895',
         "Frederick Douglass was born into slavery on Maryland's Eastern Shore around 1818. As a boy he was taught the alphabet in secret, and he went on teaching himself to read long after the lessons were forbidden.\n\nHe escaped to the North in 1838 and became the most powerful anti-slavery voice in the country. His 1845 memoir, Narrative of the Life of Frederick Douglass, was read on both sides of the Atlantic, and the newspaper he founded, The North Star, carried the words \"Right is of no sex — Truth is of no color.\"\n\nHe advised President Lincoln during the Civil War, pressed for Black soldiers to serve and to be paid equally, and spent the rest of his life arguing that the promises of the Constitution belonged to everyone. He died in 1895."],
        ['tubman', 'Harriet Tubman', 'Freedom Fighter & Humanitarian', 'trailblazers', 'c. 1822 – 1913',
         "Harriet Tubman was born into slavery in Dorchester County, Maryland, around 1822. A head injury inflicted by an overseer when she was a girl left her with pain and blackouts for the rest of her life.\n\nShe escaped in 1849 — and then went back. Over roughly a decade she returned to the South again and again as a conductor on the Underground Railroad, guiding about seventy people to freedom. She often said she never lost a passenger.\n\nDuring the Civil War she served the Union as a cook, a nurse, a scout and a spy, and helped lead the raid at Combahee Ferry in 1863 that freed more than seven hundred people in one night. She spent her later years in Auburn, New York, caring for the elderly and campaigning for women's suffrage, and died in 1913."],
        ['washington', 'Booker T. Washington', 'Educator & Advisor', 'trailblazers', '1856 – 1915',
         "Booker T. Washington was born into slavery in Virginia in 1856 and was freed as a child at the end of the Civil War. He walked much of the way to Hampton Institute to get an education, working as a janitor to pay his keep.\n\nIn 1881 he was chosen to lead a new school for Black students in Alabama. Tuskegee began with a handful of students in a shanty and grew, under his hand, into one of the most important institutions in Black America.\n\nHis autobiography, Up From Slavery, made him famous, and he advised presidents and raised money for schools across the South. His emphasis on trades and self-reliance was debated fiercely in his own lifetime — a debate worth understanding, because it shaped a generation. He died in 1915."],
        ['walker', 'Madam C.J. Walker', 'Entrepreneur & Philanthropist', 'trailblazers', '1867 – 1919',
         "Sarah Breedlove was born in 1867 in Delta, Louisiana, to parents who had been enslaved. Orphaned at seven, married at fourteen and widowed at twenty, she supported her daughter as a washerwoman.\n\nStruggling with hair loss herself, she developed her own scalp treatments and began selling them door to door as Madam C.J. Walker. She built a company with its own factory, laboratory and training school, and put thousands of Black women to work as sales agents at a time when almost no other route to independent income existed.\n\nShe became one of the first self-made women millionaires in America and gave a great deal of it away — to the NAACP, to schools, and to the campaign against lynching. She died in 1919."],
        ['marshall', 'Thurgood Marshall', 'Supreme Court Justice', 'trailblazers', '1908 – 1993',
         "Thurgood Marshall was born in Baltimore in 1908. Turned away from the University of Maryland's law school because he was Black, he went to Howard — and years later sued that same school, and won.\n\nAs the lawyer for the NAACP Legal Defense Fund he argued case after case against segregation, travelling the South at real personal risk. In 1954 he won Brown v. Board of Education, in which the Supreme Court held that separate schools could never be equal.\n\nIn 1967 he became the first Black Justice of the Supreme Court, where he served for twenty-four years as a steady voice for individual rights. He died in 1993."],

        ['latimer', 'Lewis Latimer', 'Improved the light bulb and electrical systems', 'inventions', '1848 – 1928',
         "Lewis Latimer was born in Massachusetts in 1848, the son of parents who had escaped slavery in Virginia. He joined the Navy at sixteen, then taught himself mechanical drawing while working as an office boy at a patent firm.\n\nHe became one of the finest draftsmen of his day. He drew the plans for Alexander Graham Bell's telephone patent, and in 1882 he patented a better process for making the carbon filaments inside light bulbs — cheaper and longer lasting, which helped put electric light within ordinary reach.\n\nHe supervised the installation of electric lighting in New York, Philadelphia, Montreal and London, and was the only Black member of the Edison Pioneers. He died in 1928."],
        ['miles', 'Alexander Miles', 'Invented the automatic elevator doors', 'inventions', '1838 – 1918',
         "Alexander Miles was born in Ohio in 1838 and built a successful life as a barber and businessman in Duluth, Minnesota.\n\nIn the elevators of his day, both the shaft door and the car door had to be opened and closed by hand — and people fell down open shafts. After watching his own daughter step too close to one, Miles designed a mechanism that opened and closed the doors automatically as the car arrived and left. He patented it in 1887.\n\nThe idea is in every elevator you have ever ridden. He died in 1918."],
        ['morgan', 'Garrett Morgan', 'Invented the traffic signal', 'inventions', '1877 – 1963',
         "Garrett Morgan was born in Paris, Kentucky, in 1877, the son of formerly enslaved parents, and left school early to work. He settled in Cleveland and opened a sewing machine repair shop, then a tailoring business.\n\nHe invented a safety hood that let a person breathe in smoke and fumes — an early gas mask. In 1916 he used it himself, going down into a gas-filled tunnel under Lake Erie to bring trapped workers out alive.\n\nAfter seeing a bad collision at an intersection he patented a three-position traffic signal in 1923, adding the warning interval between stop and go that every traffic light still uses. He died in 1963."],
        ['jennings', 'Thomas L. Jennings', 'Invented the dry cleaning process', 'inventions', '1791 – 1856',
         "Thomas L. Jennings was born free in New York City in 1791 and became a well-regarded tailor and clothier there.\n\nCustomers kept asking him what could be done about clothes too delicate to wash. In 1821 he patented a method he called dry scouring — an early form of dry cleaning — and is believed to be the first African American to receive a United States patent.\n\nHe spent the money it earned him on freedom: buying his family out of slavery, and funding abolitionist work and Black civil rights organisations in New York for the rest of his life. He died in 1856."],

        ['chisholm', 'Shirley Chisholm', '1st Black Woman in Congress', 'politics', '1924 – 2005',
         "Shirley Chisholm was born in Brooklyn in 1924 to parents from Barbados and Guyana, and began her working life as a teacher and an expert on early childhood education.\n\nIn 1968 she became the first Black woman elected to the United States Congress, representing Brooklyn for seven terms. She hired an all-women staff, fought for food stamps and for domestic workers to be covered by the minimum wage, and helped found the Congressional Black Caucus.\n\nIn 1972 she ran for the Democratic nomination for president — the first Black candidate to do so for a major party, and the first woman to run in the Democratic primaries. Her campaign slogan was the title of her book: Unbought and Unbossed. She died in 2005."],
        ['obama', 'Barack Obama', '44th President of the United States', 'politics', 'born 1961',
         "Barack Obama was born in Honolulu in 1961 and raised largely by his mother and grandparents. He worked as a community organiser on the South Side of Chicago before studying law at Harvard, where he was the first Black president of the Harvard Law Review.\n\nHe served in the Illinois State Senate and then the United States Senate, and in 2008 was elected the 44th President of the United States — the first Black American to hold the office. He was re-elected in 2012.\n\nHis two terms brought the Affordable Care Act, the recovery from the 2008 financial crisis, and the restoration of relations with Cuba. He was awarded the Nobel Peace Prize in 2009."],
        ['harris', 'Kamala Harris', '1st Black & South Asian Vice President', 'politics', 'born 1964',
         "Kamala Harris was born in Oakland, California, in 1964, to a mother from India and a father from Jamaica. She graduated from Howard University and the University of California's Hastings College of the Law.\n\nShe served as District Attorney of San Francisco, then as Attorney General of California, and was elected to the United States Senate in 2016.\n\nIn 2021 she was sworn in as the 49th Vice President of the United States — the first woman, the first Black American and the first South Asian American to hold the office — and served through January 2025."],
        ['powell', 'Colin Powell', 'Chairman of the Joint Chiefs of Staff', 'politics', '1937 – 2021',
         "Colin Powell was born in Harlem in 1937 to Jamaican immigrant parents and grew up in the South Bronx. He joined the ROTC at City College of New York and found, as he later put it, the thing he was good at.\n\nHe served two tours in Vietnam, rose to four-star general, and in 1989 became the first Black Chairman of the Joint Chiefs of Staff — the highest military position in the Department of Defense — a post he held through the Gulf War.\n\nIn 2001 he became the first Black Secretary of State. He received two Presidential Medals of Freedom, and died in 2021."],
        ['rice', 'Condoleezza Rice', '1st Black Woman Secretary of State', 'politics', 'born 1954',
         "Condoleezza Rice was born in Birmingham, Alabama, in 1954 and grew up in a city of bombings and segregation; a girl she knew was killed in the 16th Street Baptist Church bombing in 1963.\n\nShe entered university at fifteen intending to be a concert pianist, changed course to Soviet studies, and by 1993 was the provost of Stanford University — the first woman, and the first Black person, to hold that job.\n\nShe served as National Security Advisor from 2001, and in 2005 became the first Black woman to serve as Secretary of State. She has since returned to Stanford."],

        ['williams', 'Dr. Daniel Hale Williams', 'Pioneered open-heart surgery', 'science', '1856 – 1931',
         "Daniel Hale Williams was born in Pennsylvania in 1856 and apprenticed as a shoemaker and then a barber before working his way into medical school in Chicago.\n\nBlack doctors were barred from the city's hospitals and Black nurses could not train in them, so in 1891 he founded Provident Hospital — the first hospital in America owned by Black Americans, with an interracial staff and its own nursing school.\n\nIn 1893 he operated on a man stabbed in the chest, opening the chest and repairing the sac around the heart. The patient lived for years afterwards. It is remembered as one of the first successful heart surgeries on record. He died in 1931."],
        ['jemison', 'Dr. Mae Jemison', '1st Black Woman in Space', 'science', 'born 1956',
         "Mae Jemison was born in Alabama in 1956 and raised in Chicago, where she was told that a girl interested in science should think about nursing. She entered Stanford at sixteen instead, and went on to medical school at Cornell.\n\nShe practised medicine and served as a Peace Corps medical officer in Sierra Leone and Liberia before applying to NASA. In September 1992 she flew aboard the space shuttle Endeavour as a science mission specialist — the first Black woman to go to space.\n\nShe left NASA to start her own technology company and to teach, and has spent much of her life since encouraging children, particularly girls, into science."],
        ['carver', 'George Washington Carver', 'Innovative Scientist & Inventor', 'science', 'c. 1864 – 1943',
         "George Washington Carver was born into slavery in Missouri near the end of the Civil War. Sickly as a child, he was drawn to plants early and became known locally as the boy who could make anything grow.\n\nHe was turned away from one college when they discovered he was Black, and eventually earned his degrees at Iowa State. In 1896 Booker T. Washington invited him to Tuskegee, where he stayed for the rest of his life.\n\nCotton had exhausted the soil across the South. Carver taught poor farmers to restore it by rotating in peanuts and sweet potatoes, then found hundreds of uses for those crops so the harvest would be worth something. He published his findings in plain language, for free, and died in 1943."],

        ['robinson', 'Jackie Robinson', "Broke baseball's colour barrier, 1947", 'sports', '1919 – 1972',
         "Jackie Robinson was born in Georgia in 1919 and raised in Pasadena, California, where he became the first athlete at UCLA to letter in four sports. He was court-martialled in the Army for refusing to move to the back of a bus — and acquitted.\n\nOn 15 April 1947 he took the field for the Brooklyn Dodgers and ended sixty years of segregation in Major League Baseball. He had agreed with Branch Rickey not to answer the abuse for his first two seasons, and he kept his word while it came from the stands, the opposing dugouts and sometimes his own.\n\nHe was Rookie of the Year in 1947, Most Valuable Player in 1949, and elected to the Hall of Fame in 1962. His number 42 is retired across all of baseball. He died in 1972."],
        ['rudolph', 'Wilma Rudolph', 'Three Olympic golds, 1960', 'sports', '1940 – 1994',
         "Wilma Rudolph was born in Tennessee in 1940, the twentieth of twenty-two children, weighing four and a half pounds. Scarlet fever, pneumonia and polio left her left leg weakened, and she wore a brace until she was about twelve.\n\nHer family took turns massaging her leg every day for years. By high school she was a basketball star, and by sixteen she had an Olympic bronze medal.\n\nAt the 1960 Rome Olympics she won the 100 metres, the 200 metres and the 4x100 metre relay — the first American woman to win three golds at a single Games. She came home and refused to attend a segregated welcome parade, so her hometown held its first integrated public event instead. She died in 1994."],
        ['ali', 'Muhammad Ali', 'Champion in the ring and for conscience', 'sports', '1942 – 2016',
         "Cassius Clay was born in Louisville, Kentucky, in 1942 and started boxing at twelve, after his bicycle was stolen and a policeman told him he had better learn to fight first.\n\nHe won Olympic gold in Rome in 1960 and took the heavyweight title from Sonny Liston in 1964. He joined the Nation of Islam and took the name Muhammad Ali.\n\nIn 1967 he refused induction into the army on religious grounds and was stripped of his title and barred from boxing for over three years, in the prime of his career. The Supreme Court overturned his conviction in 1971, and he won the title back in Zaire in 1974. He spent his later years, living with Parkinson's disease, as a humanitarian, and died in 2016."],
        ['gibson', 'Althea Gibson', '1st Black champion at Wimbledon', 'sports', '1927 – 2003',
         "Althea Gibson was born in South Carolina in 1927 and grew up in Harlem, learning the game on the paddle tennis courts of 143rd Street.\n\nTennis was closed to Black players at the highest level until 1950, when she became the first to compete in the U.S. National Championships; she played Wimbledon for the first time the year after.\n\nShe won the French Championships in 1956 and then took both Wimbledon and the U.S. Nationals in 1957 and again in 1958. Later she became the first Black woman on the professional golf tour. She died in 2003."],
    ];

    $sort = [];
    foreach ($rows as $r) {
        list($slug, $name, $role, $cat, $years, $body) = $r;
        try {
            if (one("SELECT id FROM aah_people WHERE slug=?", [$slug])) continue;
            $photo = is_file(dirname(__DIR__) . '/public/assets/aahistory/' . $slug . '.jpg')
                   ? 'assets/aahistory/' . $slug . '.jpg' : '';
            $n = isset($sort[$cat]) ? ++$sort[$cat] : ($sort[$cat] = 0);
            q("INSERT INTO aah_people (slug,name,role,category,photo,born,body,sort) VALUES (?,?,?,?,?,?,?,?)",
              [$slug, $name, $role, $cat, $photo, $years, $body, $n]);
        } catch (\Throwable $e) { /* one bad row must not stop the page loading */ }
    }
    aah_meta_set('seeded', '1');
}
