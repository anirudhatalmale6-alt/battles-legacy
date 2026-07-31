<?php
require __DIR__ . '/../src/bootstrap.php';

/* The family's "Our History" section. All text and images are transcribed directly from the
   family history book (Family History.pdf) supplied by the family — written by Rodney Battles
   and Annie Pearl Battles Hale. Nothing here is invented. Chapters whose only content in the
   book is photographs present those photographs with their original captions. */

/* helper: render a labelled image with a gold-framed caption */
function h_img($file, $cap = '') {
    // caption may contain intended HTML entities (curly quotes etc.); render it raw,
    // and derive a plain-text alt from the decoded caption.
    $alt = e(html_entity_decode($cap, ENT_QUOTES, 'UTF-8'));
    $h = '<figure class="hf-fig"><img src="assets/history/' . e($file) . '" alt="' . $alt . '">';
    if ($cap !== '') $h .= '<figcaption>' . $cap . '</figcaption>';
    return $h . '</figure>';
}
/* helper: render a census-style table */
function h_tbl($title, $cols, $rows) {
    $h = '<div class="census">';
    if ($title) $h .= '<div class="census-cap">' . e($title) . '</div>';
    $h .= '<div class="census-scroll"><table><thead><tr>';
    foreach ($cols as $c) $h .= '<th>' . e($c) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        foreach ($r as $cell) $h .= '<td>' . e($cell) . '</td>';
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ---- build the census tables used inside the chapter bodies ---- */
$T_JOHNSON1880 = h_tbl('1880 Census Report — William Johnson household',
  ['Name','Race','Sex','Age','Relation','Occupation','Birthplace'],
  [
    ['Johnson, William','B','M','26','Head','Farming','TX'],
    ['Johnson, Susan','B','F','35','Wife','Housekeeping','GA'],
    ['Johnson, Anna','B','F','11','Daughter','Farm Laborer','TX'],
    ['Johnson, Theodoshius','B','M','7','Son','—','TX'],
    ['Johnson, J.H.B.','B','M','5','Son','—','TX'],
  ]);

$T_RICHMOND1880 = h_tbl('1880 Census Report — Richmond & Louisa Battles',
  ['Name','Race','Sex','Age','Relation','Occupation','Birthplace','Father','Mother'],
  [
    ['Battles, Richmond','Mu','M','48','Head','Farming','MS','—','—'],
    ['Battles, Louisa','Mu','F','36','Wife','Housekeeping','GA','GA','GA'],
    ['Daniels, William','B','M','16','Nephew','Farm Laborer','TX','AL','AL'],
    ['Williams, Horatio','B','M','11','Nephew','—','TX','GA','AL'],
  ]);

$T_1900_HORATIO = h_tbl('1900 Census Report — Horatio Battles household',
  ['Name','Age','Relationship'],
  [
    ['Horacio Battles','30','Head'],
    ['Lizzie Battles','25','Wife'],
    ['Edmond Battles','3','Son'],
    ['Sussie Battles','2','Daughter'],
    ['Calvin Battles','2 months','Son'],
  ]);

$T_1900_DANIELS = h_tbl('1900 Census Report — William Daniels household',
  ['Name','Age','Relationship'],
  [
    ['William Daniels','35','Head'],
    ['Carrie Daniels','25','Wife'],
    ['Nora Daniels','11','Daughter'],
    ['Salisie Daniels','10','Daughter'],
    ['Oscar Daniels','8','Son'],
    ['Clara Daniels','6','Daughter'],
    ['Achsah Daniels','5','Daughter'],
    ['Joe Daniels','13','Son'],
  ]);

$T_1910_JOHNSON = h_tbl('1910 Census Report — William Johnson household',
  ['Name','Relation','Sex','Race','Age','Status','Birthplace','Father','Mother'],
  [
    ['Johnson, William','Head','M','Mu','56','Married (maybe widowed)','TX','SC','GA'],
    ['Battles, Louizer','Cousin','F','B','64','Widow','GA','GA','GA'],
    ['Sarr, Willie','Grandson','M','B','10','S','TX','TX','TX'],
  ]);

$T_1910_JOHN = h_tbl('1910 Census Report — John Johnson household',
  ['Name','Relation','Sex','Race','Age','Marriage','Birthplace','Father','Mother'],
  [
    ['Johnson, John','Head','M','B','34','1st','TX','TX','GA'],
    ['Johnson, Kate','Wife','F','B','26','1st','TX','TX','TX'],
    ['Johnson, Lillian','Daughter','F','B','6','S','TX','TX','TX'],
  ]);

$byline = '<p class="byline">— Written by Rodney Battles</p>';

/* =================== CHAPTERS =================== */
$CHAPTERS = [];

$CHAPTERS[] = [
 'n'=>1,'slug'=>'thank-you','title'=>'A Special Thank You','date'=>'Jan 22 2011',
 'card'=>'annie.jpg',
 'lead'=>"I would like to whole heartedly thank Rodney Augustus Battles and Annie Pearl Hale for their untiring dedication and hard work in researching our family history.",
 'body'=>
   '<p>I would like to whole heartedly thank Rodney Augustus Battles and Annie Pearl Hale for their untiring dedication and hard work in researching information regarding our family history. Their gathering of information has given us all a view into our past that helps us understand our present. This information has introduced all of us to family members that we did not know existed and will allow each of us to share our family with other families.</p>'.
   '<p>Again, thank you Rodney and Annie for being so diligent in your pursuit of this great quest.</p>'.
   h_img('annie.jpg','Annie Pearl Battles Hale').
   '<p class="byline">— Annie Pearl Battles Hale</p>',
];

$CHAPTERS[] = [
 'n'=>2,'slug'=>'introduction','title'=>'Introduction','date'=>'Jan 15 2011',
 'card'=>'',
 'lead'=>"In 1998, W.J. Battles organized a Labor Day Reunion for the descendants of Gus and Angie Battles. After viewing old photos, we realized we didn't know very much about our family history.",
 'body'=>
   '<p>In 1998, W.J. Battles organized a Labor Day Reunion for the descendants of Gus and Angie Battles. In 1999, Javaun &ldquo;Ree Ree&rdquo; Smith Jackson organized another reunion. At the 1999 reunion, we toured neighborhoods in Fort Worth to see some of the houses where our parents and grandparents had lived and where we had grown up. During this weekend event, we also shared family photos and discussed the family, or as much as we knew about it. After viewing some old photos that W.J. had, we realized we didn&rsquo;t know very much about our family history.</p>'.
   '<p>During the 1999 reunion I volunteered to research the family&rsquo;s history. Purely by a stroke of luck, I was fortunate to locate a lady named Annie Pearl Battles Hale on the Internet. Early in the process, I sent e-mails to several people whose last name was Battles to see if we might be related in any way. One white lady replied to my email by telling me she definitely was not related to me, but she knew someone who might be. She passed my e-mail address along to Annie who e-mailed me and told me she was also a great granddaughter of Horatio Battles. Annie is the granddaughter of Calvin Sam &ldquo;Uncle Sam&rdquo; Battles by Sam&rsquo;s first wife. Those of us in Fort Worth who knew Uncle Sam and Aunt Nealie didn&rsquo;t know Uncle Sam had a first wife&mdash;so just the knowledge of Annie&rsquo;s existence was an exciting revelation to me.</p>',
];

$CHAPTERS[] = [
 'n'=>3,'slug'=>'richmond-battles','title'=>'Richmond Battles','date'=>'Jul 14 2012',
 'card'=>'ancestor.jpg',
 'lead'=>"Sometime around 1814, a white man named John N. Battles purchased several slaves in Norfolk, Virginia and transported them to Monroe County, Mississippi Territory. One had a son who would become known as Richmond Battles.",
 'body'=>
   '<p>A few slaves were imported from Africa as early as 1619. With the spread of tobacco farming in the 1670&rsquo;s and the diminishing number of people willing to sign-on as indentured servants in the 1680&rsquo;s, increasing numbers of slaves were brought in from Africa. They replaced Native American slaves, who were found to be susceptible to diseases.</p>'.
   '<p>Sometime around 1814, a white man named John N. Battles purchased several slaves in Norfolk, Virginia and transported them to Monroe County, Mississippi Territory, where John Battles owned 120 acres of land. One of the slaves John Battles purchased had a son who would become known as Richmond Battles.</p>'.
   '<p>The slave that John Battles purchased had at least one brother who was purchased by another plantation owner whose last name was King. Several years ago, Myrtle Hunt Thomas met a Reverend King in Fort Worth, who said he was a grandson of the slave who had taken the King surname after the slaves were freed.</p>'.
   '<p>Richmond was born in 1832 according to notes that were written in the family Bible. Richmond&rsquo;s mother was a Creek Indian, and he was a good-looking man. Although Richmond&rsquo;s father was a slave, Richmond never had slave status because children born from black slave men and Creek women were considered full members of their mother&rsquo;s clans and of the tribe, which meant Richmond was a freeman.</p>'.
   h_img('ancestor.jpg','An early Battles family portrait, from the family history book').
   '<p>After Andrew Jackson&rsquo;s defeat of the Creek Indians in 1814, more and more settlers from the upper South began flocking to the dark, rich soils of Mississippi. The territory&rsquo;s cotton economy boomed in the 1820s as large numbers of slaves were imported to work the cotton fields. From 1810 to 1820, the enslaved population on the Mississippi frontier grew by more than 90 percent. By 1830, the slave population rose to nearly 66,000 persons. Slave children were sent into the fields at about twelve years of age, where they worked from sun up to sun down. The life expectancy for a twenty-year-old black male in Mississippi in 1850 was thirty-seven years.</p>'.
   '<p>When the slaves were freed in 1863, Richmond&rsquo;s father would have been between 68 and 72 years old if he was still living. Richmond took the last name of his father&rsquo;s slave owner and moved to Georgia for two years.</p>'.
   '<p>While Richmond Battles was in Georgia, he met a woman named Susan. He also met a woman named Louisa. According to family members, Susan and Louisa followed Richmond to Smith County, Texas in 1865. Susan was pregnant and lived in the Canton Beat next door to Richmond and Louisa. Susan and Louisa did not get along.</p>'.
   '<p>Susan Mipus was born in Georgia in 1844 and was light complexioned. Prior to moving to Texas, Susan had given birth to a son in 1864 named William Daniels. William&rsquo;s father was an Indian. When Susan moved to Smith County in 1865, she brought William Daniels with her.</p>'.
   '<p>Louisa Battles was born in Georgia between 1844 and 1846 and was the daughter of a black mother and a rich white, Georgia plantation owner. Louisa was well educated and she dressed well. Her father claimed her and took care of her financially&mdash;even after she married Richmond in 1865.</p>'.
   '<p>On September 29, 1865, Susan gave birth to a second son and named him Horatio. In 1868, Susan gave birth to another son named Cheris. Richmond Battles was Horatio&rsquo;s and Cheris&rsquo; father, but Richmond&rsquo;s wife Louisa didn&rsquo;t know it. Cheris died in 1869. That same year, Susan gave birth to a daughter named Anna.</p>'.
   '<p>For many years, the relationship between Richmond, Louisa, Susan, Horatio, and William Daniels was a mystery. The entries on lines 19&ndash;24 of the 1870 Census cleared up the mystery by showing that Susan Mipus was the head of household, lived next door to Richmond and Louisa Battles, and Susan had three children: William, age 6; Horatio, age 4; and Hannah, age 1. In this census report, Richmond&rsquo;s last name was spelled as Batterly instead of Battles, and Anna&rsquo;s name was spelled as Hannah. At the time this census was taken, Richmond was 26, which if accurate, means he was born in 1844, not 1832.</p>'.
   '<p>Shortly after the 1870 Census was taken, Louisa made it known to Richmond that she didn&rsquo;t want to have children. Richmond reportedly told her, &ldquo;Then you can help me raise mine.&rdquo; When Louisa asked Richmond what he meant, Richmond&rsquo;s answer was, &ldquo;I&rsquo;ll show you.&rdquo; That year, Horatio and his half-brother William Daniels moved in with Richmond and Louisa. Louisa Battles became known as &ldquo;Auntie&rdquo;.</p>'.
   '<p>Although Susan and Richmond never married, Susan was the first lady of the Battles family. She married Anna&rsquo;s father, William &ldquo;Bill&rdquo; Johnson, a mulatto, on February 26, 1874 and they had a total of five children.</p>'.
   '<p>The 1870 Census Report lists Richmond and Louisa&rsquo;s race as &ldquo;black.&rdquo; The 1880 Census Report lists Richmond and Louisa as mulattos. The entries on lines 30&ndash;33 of the 1880 Census Report show Horatio&rsquo;s last name as &lsquo;Williams&rsquo; and indicate he is a nephew of Richmond Battles. Reportedly, Louisa was so hateful she didn&rsquo;t want to use the name Battles for Horatio, so she told the census taker his last name was Williams. The entries on lines 34&ndash;38 also show that Horatio&rsquo;s mother Susan was married to William Johnson with three children living at home.</p>'.
   '<p>Most of the 1890 Census Reports were destroyed by a fire. The entries on lines 17&ndash;21 indicate Horatio Battles was married to Lizzie with three children living at home: Edmond, Susie, and Calvin. (The census taker incorrectly spelled Settie&rsquo;s name as Sussie, and Calvin was actually Sam, whose middle name was Calvin.)</p>'.
   '<p>In the 1910 Census, William Johnson is listed as &ldquo;maybe widowed.&rdquo; Louisa Battles, whose name was mistakenly spelled &ldquo;Louizer,&rdquo; was living with William and listed as his cousin. Louisa died April 5, 1916 in Victoria County, Texas at the age of 70. Richmond Battles died in 1932 according to notes in the family Bible. His burial place is not known.</p>'.
   $byline,
];

$CHAPTERS[] = [
 'n'=>4,'slug'=>'acknowledgments','title'=>'Acknowledgments','date'=>'Jan 15 2011',
 'card'=>'',
 'lead'=>"Annie Pearl Battles Hale worked diligently to provide information, obituaries, and photographs for many of the Battles family members in East Texas for the first edition of the Battles' Book.",
 'body'=>
   '<p>Annie Pearl Battles Hale worked diligently to provide information, copies of obituaries, and photographs for many of the Battles family members in East Texas for the first edition of the Battles&rsquo; Book. I am truly grateful to her for assisting in bringing the initial project to fruition. A great deal of the information that Annie provided to me was provided to her by Ms. Eulalia Choice in Tyler, Texas. Eulalia was Annie&rsquo;s aunt and the daughter of Susie Choice, whose place in the family is documented in the pages of this book.</p>'.
   '<p>In June 1995, Lafane &ldquo;Golly&rdquo; Battles, his wife, Kathryn, and their daughter Lavonne visited Myrtle Hunt Thomas, daughter of Settie Alma Battles Hunt. Lafane videotaped their visit. I am thankful to Lafane for providing me with a copy of the tape.</p>'.
   '<p>I&rsquo;d also like to acknowledge Johnnie Battles and Myrtle Hunt Thomas for the photographs and information they so graciously shared with me about our family. A heartfelt thanks goes out to the descendants of Gus and Angie Battles for the information and photographs that have provided for our branches of the family. I am particularly grateful to Diana Lenzy Kelley for several of the photographs that are included in this edition of our family&rsquo;s history.</p>'.
   '<p>Lastly, I&rsquo;d like to acknowledge Mr. Calvin Littlejohn (1909&ndash;1993) whose work as a professional photographer has been credited for providing a comprehensive portrait of the African-American experience in Fort Worth and Tarrant County during the turbulent period of segregation and beyond.</p>'.
   '<p>Mr. Littlejohn was born in Arkansas. He moved to Fort Worth, Texas in the 1930s and established his commercial photography studio in the Fort Worth area in 1934. World War II interrupted his photographic work while he served as an Army private at Ft. Leonard Wood in Missouri, but upon his return Littlejohn began expanding his scope to include capturing recreation hall parties, speaking engagements, visiting celebrities, church events, school activities, and other everyday events which produced more candid images than his studio portrait work.</p>'.
   '<p>During the 1950s and 60s, Littlejohn&rsquo;s Studio was located on 607 Bryan Street, four blocks north of Gus Battles&rsquo; Love Sanctuary Church of God in Christ. After Calvin Littlejohn&rsquo;s death in 1993, his wife, Lucretia &ldquo;Lou,&rdquo; donated her husband&rsquo;s 70,000 plus photographs and negatives to the University of Texas at Arlington. In December 2001, most of the collection was physically transferred to the Center for American History at the University of Texas campus in Austin.</p>'.
   '<p>As prominent members of Fort Worth&rsquo;s African-American community in the 1940s and 50s, Mr. Littlejohn eternalized several images of the Battles family on film. I am pleased to include some of the priceless images from Littlejohn&rsquo;s collection in this edition of the family book. All photographs from the Calvin Littlejohn Collection at the University of Texas &ndash; Center for American History have been designated with the appropriate credit.</p>',
];

$CHAPTERS[] = [
 'n'=>5,'slug'=>'william-bill-johnson','title'=>'William "Bill" Johnson','date'=>'Jan 15 2011',
 'card'=>'johnson.jpg',
 'lead'=>"Part black and part white, with blue eyes, he looked like a white man and was known to curse prolifically. He married Susan and, together, they raised a family of five.",
 'body'=>
   h_img('johnson.jpg','William &ldquo;Bill&rdquo; Johnson, husband of Susan and father of five Johnson children.').
   '<p>William &ldquo;Bill&rdquo; Johnson is listed in the 1880 Census Report as black. The 1910 Census Report listed him as mulatto. According to family members, he was part black and part white with blue eyes and looked like a white man. He was known to curse prolifically. He was born in 1854 or 1855 in Texas. His father was born in South Carolina; his mother was born in Georgia. William Johnson died September 27, 1927.</p>'.
   '<p>The following information is recorded in the 1880 Census Report. The information going across for each person is: name, color/race, gender, age, relation to head of household, occupation, and place of birth.</p>'.
   $T_JOHNSON1880.
   '<p>According to family members, William and Susan&rsquo;s children were:</p>'.
   '<ol class="fam-list"><li>Anna Johnson, b. 1869</li><li>Theodoshius Johnson, b. 1872/73</li><li>John Henry Johnson, b. 1874/75</li><li>Susan &ldquo;Susie&rdquo; Johnson, b. May 1, 1882, d. May 10, 1974</li><li>Dosheres Johnson</li></ol>',
];

$CHAPTERS[] = [
 'n'=>6,'slug'=>'about-the-us-census','title'=>'About The U.S. Census','date'=>'Nov 09 2011',
 'card'=>'',
 'lead'=>"Researching a family's history most often begins with the U.S. Census Reports. The Constitution mandates that the census be taken at least once every 10 years.",
 'body'=>
   '<p>Researching a family&rsquo;s history most often begins with the U.S. Census Reports. The United States Constitution mandates that the census be taken at least once every 10 years, and that the number of members of the United States House of Representatives from each state be determined accordingly. In addition, census statistics are used for apportioning Federal funding for many social and economic programs.</p>'.
   '<p>The first U.S. Census was conducted in 1790 by Federal marshals. Census takers went door-to-door and recorded the number of people in each household, along with the name of the head of the household. Slaves were enumerated, but for apportionment purposes each counted as only three-fifths of a citizen. American Indians, being neither taxed nor considered during apportionment, were not counted in the census. The first census counted 3.9 million people, less than half the population of New York City in 2000. The 2000 census counted over 281 million people.</p>'.
   '<p>In 1902, Congress established the Census Bureau as a permanent federal agency. In order to protect an individual&rsquo;s privacy, the federal government enacted a law on October 5, 1978 whereby census records are sealed for 72 years. Thus, the most recent Census released to the public was the 1930 Census, released in 2002. The 1940 Census will be released to the public on Sunday, April 1, 2012.</p>'.
   '<p>A gentleman in St. Louis by the name of Robert Battle kindly volunteered to help me obtain access to the U.S. Census Reports that were taken between 1790 and 1930. Having searched the reports himself, Robert informed me that many of the early black citizens of the United States were remarkably adept at avoiding all official documentation. Many took the attitude: &ldquo;Here comes that census guy!&rdquo; They didn&rsquo;t answer the door. Fortunately, some information for our ancestors was found in the 1870, 1880, 1900, 1910, 1920, and 1930 Census Reports. A fire destroyed most of the 1890 Census Reports.</p>'.
   '<p>The names, ages, and birthplaces of individuals counted in the early census reports vary widely from year to year, usually due to two factors: the person giving the information&mdash;and more importantly&mdash;the person recording the information. Many times, if no one were home, the census taker would obtain information from a neighbor.</p>'.
   '<p>There are inconsistencies, misspelled names, and other incorrect information listed in the census reports for our family. For example, the 1880 Census Report shows William Daniels&rsquo; (Horatio Battles&rsquo; half-brother) mother&rsquo;s and father&rsquo;s place of birth as Alabama. Later census reports indicate William Daniels&rsquo; mother and father were born in Georgia. The 1880 Census Report shows that Horatio Battles&rsquo; father was born in Georgia and his mother in Alabama. Later census reports show that Horatio&rsquo;s mother and father were born in Georgia.</p>'.
   '<p>Family members cleared up many of the errors contained in census reports. Another key part of researching a family&rsquo;s history is to examine the birth and death records available to the public by some, but not all, states. Birth and death records for Texas residents are available from the Texas Department of Health Bureau of Vital Statistics, but only if a birth or death certificate was issued.</p>'.
   '<p>During the 19th and early 20th centuries, children were often born at home with the assistance of a midwife and no birth certificate was ever issued. Likewise, many people were buried in the 19th and early 20th centuries without a death certificate being issued. In these cases, there is no public record of the births or deaths.</p>'.
   '<p>Several members of the third and fourth generations of our family were Masons or members of the Heroines of Jericho, the Order of the Eastern Star, or the Independent Order of Odd Fellows. The Heroines of Jericho is an androgynous degree conferred in America on Royal Arch Masons, their wives, mothers, widows, sisters, and daughters. It is intended to instruct its female recipients in the high and noble principles inculcated in the degrees which will appeal to the better instincts of the human mind.</p>'.
   '<p>The Order of the Eastern Star is the largest fraternal organization in the world that both men and women can join. It was established in 1850 by Robert Morris, a lawyer and educator from Boston, Massachusetts who had been an official with the Freemasons. It is based on teachings from the Bible, but is open to people of all monotheistic faiths.</p>'.
   '<p>The Independent Order of Odd Fellows began as a secret, fraternal, benefit society, founded in England sometime during the second quarter of the 18th century. The principal Odd Fellows emblem is the three links, standing for the virtues of friendship, love, and truth. The duties enjoined upon Odd Fellows are to visit the sick, relieve the distressed, bury the dead, and educate the orphaned.</p>'.
   $byline,
];

$CHAPTERS[] = [
 'n'=>7,'slug'=>'census-report-1870','title'=>'Census Report 1870','date'=>'Jul 14 2012',
 'card'=>'census1870.jpg',
 'lead'=>"In the 1870 Census Report, Richmond's name is incorrectly spelled as Richmond Batterley. His age is incorrectly listed as 26; his wife Louisa's as 28.",
 'body'=>
   '<p>In the 1870 Census Report, Richmond&rsquo;s name is incorrectly spelled as Richmond Batterley. His age is incorrectly listed as 26. His wife Louisa&rsquo;s age is incorrectly listed as 28. Based on the dates of birth provided by the family and on the 1880 Census Report, Richmond would have been 38 years old in 1870; Louisa would have been between 24 and 26 years old. Someone other than Richmond or Louisa obviously provided the erroneous information recorded in the 1870 Census Report.</p>'.
   '<p>The 1870 Census Report also lists Richmond and Louisa&rsquo;s race as &ldquo;black.&rdquo; The 1880 Census Report lists Richmond and Louisa as mulattos. According to family members, Richmond was part black and part Creek Indian and was a good-looking man.</p>'.
   h_img('census1870.jpg','1870 Census Report — Canton Beat, Smith County, Texas (Richmond & Louisa marked)').
   '<p>Although her last name cannot be deciphered in the 1870 Census Report, Susan was listed as the head of her household and a farm laborer with three children: William, age 6; Horatio, age 4; and Hannah, age 1. Whoever provided the information to the census taker mistakenly thought Susan&rsquo;s youngest child was a girl named Hannah&mdash;when, in fact, the girl was named Anna. Anna&rsquo;s father was a man named William &ldquo;Bill&rdquo; Johnson.</p>'.
   '<p>Richmond and Louisa&rsquo;s house was the 611th dwelling visited by the census taker in 1870. Susan&rsquo;s house was the 612th dwelling visited. From this information, it is assumed that Richmond and Susan lived in adjacent houses. At the time the 1870 Census was taken, Louisa did not know that Horatio was Richmond&rsquo;s son.</p>'.
   $byline,
];

$CHAPTERS[] = [
 'n'=>8,'slug'=>'census-report-1880','title'=>'Census Report 1880','date'=>'Jul 14 2012',
 'card'=>'',
 'lead'=>"The 1880 Census Report lists Richmond and Louisa Battles as mulattos, with William Daniels and Horatio \"Williams\" recorded as nephews of Richmond.",
 'body'=>
   '<p>The 1880 Census Report includes the following information for Richmond and Louisa Battles. The information going across for each person is: name; color/race; gender; age; relation to head of household; occupation; place of birth; place of birth of father; and place of birth of mother.</p>'.
   $T_RICHMOND1880.
   '<p>In the report, William Daniels and Horatio &ldquo;Williams&rdquo; are listed as nephews of Richmond Battles. Louisa reportedly was so hateful, she didn&rsquo;t want to use the name Battles for Horatio, so she told the census taker his last name was Williams.</p>'.
   '<p>Sometime after 1880, according to family members, Susie Johnson, John Johnson, Anna Johnson, and Dosheres Johnson also came to live with Richmond and Louisa Battles. As mentioned earlier, most of the 1890 Census Reports were destroyed by fire, so the members of Richmond&rsquo;s household cannot be verified.</p>'.
   '<p>All hell must have broken loose after the Johnson children moved in with Richmond and Louisa. When the next census was taken in 1900, neither Richmond&rsquo;s nor Louisa&rsquo;s names were listed. Horatio Battles and his half-brother, William Daniels, were heads of households. The 1900 Census Report contains the following information:</p>'.
   $T_1900_HORATIO.
   '<p>The census taker incorrectly spelled Settie&rsquo;s name as Sussie.</p>'.
   $T_1900_DANIELS.
   $byline,
];

$CHAPTERS[] = [
 'n'=>9,'slug'=>'census-report-1900','title'=>'Census Report 1900','date'=>'Jun 14 2012',
 'card'=>'',
 'lead'=>"William Daniels' son, Joe Daniels, reportedly cut a white man down on Wall Street in downtown Tyler in 1925 and had to leave town.",
 'body'=>
   '<p>William Daniels&rsquo; son, Joe Daniels, reportedly cut a white man down to the ground on Wall Street in downtown Tyler in 1925 when he was 28 and had to leave town. After the incident, Joe and Luther &ldquo;Chap&rdquo; Battles spent some time in Oklahoma. Luther was 17 at the time. Joe Daniels was never caught or tried for the incident. Luther returned to Tyler.</p>'.
   '<p>Richmond Battles&rsquo; name is not listed in the 1910 Census Report, but Louisa&rsquo;s is, although it was misspelled as Louizer. According to the family bible, Richmond Battles died in 1909 at age 76 or 77. The 1910 Census bears this out, as Louisa is listed as a widow.</p>'.
   '<p>Remember William &ldquo;Bill&rdquo; Johnson? He was the man Horatio and Cheris&rsquo;s mother Susan married after Horatio and Cheris were born. When the 1910 Census was taken, Louisa was living with William Johnson and listed as his cousin. The information going across for each person is: name, relation to head of household, gender, color/race, age, marital status, place of birth, place of birth of father, and place of birth of mother.</p>'.
   $T_1910_JOHNSON.
   '<p>Since William Johnson is listed as &ldquo;maybe widowed&rdquo; in the report, it is presumed that his wife Susan (Horatio and Cheris&rsquo;s mother) died that year. Louisa Battles died April 5, 1916 in Victoria County, Texas at age 70.</p>'.
   '<p>John Johnson, Horatio&rsquo;s half-brother, also appears in the 1910 Census Report, where the following information is recorded:</p>'.
   $T_1910_JOHN.
   $byline,
];

$CHAPTERS[] = [
 'n'=>10,'slug'=>'garfield-school','title'=>'Garfield School, Tyler, Texas','date'=>'Jan 17 2011',
 'card'=>'garfield.jpg',
 'lead'=>"Class photographs from Garfield School in Tyler, Texas, where many of the Battles family's children were educated in the early 1900s.",
 'body'=>
   '<p>These class photographs from Garfield School in Tyler, Texas are preserved in the family history book. Several of the Battles family&rsquo;s children were educated here in the early 1900s.</p>'.
   h_img('garfield.jpg','(1) Johnny Calvin Battles (son of Calvin Sam Battles) and (2) Nathaniel Battles, youngest child of Horatio and Lizzie Battles. Nathaniel and Johnny were both born in 1918. Nathaniel was Johnny&rsquo;s uncle.').
   h_img('garfield_color.jpg','Garfield School class, Tyler, Texas — a restored view of the same schoolhouse steps.'),
];

$CHAPTERS[] = [
 'n'=>11,'slug'=>'family-bible','title'=>"Horatio & Lizzie Battles' Family Bible",'date'=>'Jan 16 2011',
 'card'=>'bible_deaths.jpg',
 'lead'=>"One of the oldest bibles in Tarrant County. Recorded within its pages are the births, marriages, and deaths that preserve the lineage of the Battles family.",
 'body'=>
   '<p>Before Lizzie died, Myrtle asked if she could have the family bible. One of the oldest bibles in Tarrant County, the Fort Worth Library has attempted to persuade Myrtle to place the bible on public display, but the efforts have been unsuccessful.</p>'.
   '<p>Recorded within its pages are the births, marriages, and deaths that have preserved the legacy and lineage of the Battles family for generations.</p>'.
   h_img('bible_deaths.jpg','The &ldquo;Deaths&rdquo; page of the Battles family Bible, recording county-by-county the passing of the elder generations.').
   h_img('bible_births.jpg','The &ldquo;Births&rdquo; and &ldquo;Memoranda&rdquo; pages, alongside the tintype &ldquo;Family Portraits&rdquo; leaves.'),
];

$CHAPTERS[] = [
 'n'=>12,'slug'=>'oliver-chapel','title'=>'Oliver Chapel Cemetery','date'=>'Jan 15 2011',
 'card'=>'church.jpg',
 'lead'=>"Most of the older members of our family who lived in Tyler are buried at the Oliver Chapel Cemetery. Unfortunately, many of the gravesites are not marked.",
 'body'=>
   '<p>Most of the older members of our family who lived in Tyler are buried at the Oliver Chapel Cemetery. Unfortunately, many of the gravesites are not marked.</p>'.
   h_img('church.jpg','Oliver Chapel A.M.E. Church, near Tyler, Texas.').
   '<div class="directions"><h4>Directions to the Cemetery</h4>'.
   '<ul><li>From Tyler, take Hwy. 31 East.</li>'.
   '<li>At the intersection with FM 850, turn right (South).</li>'.
   '<li>Continue on FM 850 for two miles and turn left (East) on CR-26 (Old Jamestown Road).</li></ul>'.
   '<p>The cemetery is about 1.7 miles on the right. The gate is behind and to the right of the Oliver Chapel A.M.E. Church.</p></div>'.
   h_img('cemetery1.jpg','The grounds of the Oliver Chapel Cemetery.').
   h_img('cemetery2.jpg','Marked and unmarked graves rest beneath the trees of the churchyard.'),
];

$CHAPTERS[] = [
 'n'=>13,'slug'=>'last-family-photos','title'=>'Some of the Last Family Photos','date'=>'Feb 05 2011',
 'card'=>'family1.jpg',
 'lead'=>"A few of the last photographs taken of the family's elder generation, gathered together.",
 'body'=>
   '<p>A few of the last photographs taken of the family&rsquo;s elder generation, gathered together in their later years.</p>'.
   h_img('family1.jpg','Members of the Battles family gathered together.').
   h_img('family2.jpg','Four of the family&rsquo;s elders outside the family home.'),
];

/* =================== END CHAPTERS =================== */

/* ============================================================
   THE STORY — a flowing, plain-language narrative of the family
   saga that opens the History page. Every fact below is drawn
   directly from the family book; nothing is invented. Its job is
   to draw the reader in before they reach the detailed chapters.
   ============================================================ */
function pull($quote, $who = '') {
    $h = '<blockquote class="pull">&ldquo;' . $quote . '&rdquo;';
    if ($who) $h .= '<cite>&mdash; ' . $who . '</cite>';
    return $h . '</blockquote>';
}
$STORY_HTML =
 '<section class="story">'.
  '<p class="story-kicker">Our Story</p>'.
  '<h2>One Family, One Long Road</h2>'.
  '<p class="story-sub">Before the census reports and the family Bible, there was a story &mdash; of a free man, two women who followed him to Texas, and the generations they set in motion. Here it is, from the beginning.</p>'.

  '<p class="drop">Every person who carries the Battles name today can trace their line back to a single man: <b>Richmond Battles</b>, born in 1832 to an enslaved African father and a Creek Indian mother. And because of who his mother was, Richmond was born free &mdash; in a world built on slavery.</p>'.

  '<p>His story begins on a dock. Sometime around 1814, a white man named John N. Battles bought several slaves in Norfolk, Virginia and carried them west to Monroe County in the Mississippi Territory, where he farmed 120 acres. One of the men he purchased would father the son the family came to know as Richmond. That same man had a brother, sold to another planter named King &mdash; and generations later, a Reverend King met in Fort Worth turned out to be that brother&rsquo;s own grandson. Two branches of one African family, parted on an auction block and quietly rejoined a century later.</p>'.

  '<p>Richmond&rsquo;s mother was Creek. Under the law of her people, a child born to a Creek woman was a full member of her clan and of the tribe &mdash; so although his father wore the chains of slavery, Richmond never did. He grew into a good-looking man, and a free one. When emancipation came in 1863, he left Mississippi for Georgia and stayed two years. And there, the heart of the family story begins &mdash; because in Georgia, Richmond met two women.</p>'.

  pull('Susan and Louisa both followed Richmond to Texas in 1865. They settled next door to one another. They did not get along.').

  '<p><b>Louisa</b> was the daughter of a Black mother and a wealthy white Georgia plantation owner. She was educated, she dressed well, and her father claimed her and kept her in money all her life &mdash; even after she married Richmond in 1865. <b>Susan</b> was light-complexioned and already a mother; her first son, William Daniels, she brought with her to Texas. Both women followed Richmond to Smith County. Susan&rsquo;s house was the very next dwelling over from Richmond and Louisa&rsquo;s &mdash; and the two women could not stand one another.</p>'.

  '<p>There was a reason for the tension that Louisa did not yet know. On September 29, 1865, Susan gave birth to a son named Horatio. Three years later came another, Cheris. Richmond was their father &mdash; but his wife had no idea.</p>'.

  '<p>The truth came out in the plainest way imaginable. Sometime after 1870, Louisa told Richmond she did not want to have children of her own. His reply became a piece of family lore.</p>'.

  pull('Then you can help me raise mine.', 'Richmond Battles').

  '<p>When Louisa asked what he meant, he said only, &ldquo;I&rsquo;ll show you.&rdquo; That year, young Horatio and his half-brother William Daniels moved into Richmond and Louisa&rsquo;s home. Louisa raised them, and the children came to call her &ldquo;Auntie.&rdquo; She never quite made her peace with it: when the census taker came in 1880, she refused to give Horatio the Battles name and told the official his surname was &ldquo;Williams.&rdquo; It is written that way in the record to this day.</p>'.

  '<p>Susan, meanwhile, made her own life. Though she and Richmond never married, she was in every real sense the first lady of the family. On February 26, 1874 she married <b>William &ldquo;Bill&rdquo; Johnson</b> &mdash; a mulatto man, part Black and part white, with blue eyes, who looked white and cursed prolifically. Together they raised five children, and the Johnson and Battles lines have been braided together ever since.</p>'.

  h_img('johnson.jpg','William &ldquo;Bill&rdquo; Johnson, who married Susan &mdash; the first lady of the Battles family.').

  '<p>From Richmond and Susan&rsquo;s son Horatio, the family multiplied across East Texas and, later, Fort Worth. Horatio and his wife Lizzie kept one of the oldest family Bibles in Tarrant County, its pages crowded with births, marriages, and deaths. The children were sent to Garfield School in Tyler. The elders were laid to rest &mdash; many in unmarked graves &mdash; at the Oliver Chapel Cemetery.</p>'.

  '<p>Not every chapter was gentle. In 1925, William Daniels&rsquo; son Joe cut a white man down on Wall Street in downtown Tyler and fled &mdash; first to Oklahoma, with seventeen-year-old Luther &ldquo;Chap&rdquo; Battles at his side. Joe was never caught, never tried. Luther came home.</p>'.

  '<p class="story-close">These are the stories the census reports only hint at. On the chapters that follow you&rsquo;ll find the records themselves &mdash; the 1870, 1880, 1900 and 1910 censuses, the Bible in its own handwriting, the schoolhouse photographs &mdash; set down exactly as they were recovered. Read them in order, or wander wherever your name leads you. Either way, you are reading about people who were determined to be remembered.</p>'.

  '<p class="byline">&mdash; Compiled from the family history researched by Rodney Battles &amp; Annie Pearl Battles Hale</p>'.
 '</section>';

$bySlug = [];
foreach ($CHAPTERS as $c) $bySlug[$c['slug']] = $c;
$sel = $_GET['ch'] ?? '';
$current = $bySlug[$sel] ?? null;   // null = "All Chapters" view

/* card thumbnail: real image or a tasteful placeholder */
function hist_card_media($c) {
    if (!empty($c['card'])) {
        return '<div class="hc-img"><img src="assets/history/' . e($c['card']) . '" alt="' . e($c['title']) . '"></div>';
    }
    return '<div class="hc-img ph"><svg viewBox="0 0 24 24" class="ph-ic"><path d="M4 19V6a2 2 0 0 1 2-2h8l6 6v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 4v6h6"/></svg><span>Written record</span></div>';
}

page_head($current ? $current['title'] : 'Our History', ['body_class' => 'home hist']);
?>
<section class="hist-hero">
  <div class="hh-photo hh-left"><img src="assets/history/hero_group.jpg" alt="Battles family, early generations"></div>
  <div class="hh-center">
    <h1 class="hist-title">Our History</h1>
    <div class="hist-orn">&#10086;</div>
    <p>Preserving our stories. Honoring our ancestors. Building our legacy.</p>
  </div>
  <div class="hh-photo hh-right"><img src="assets/history/hero_bible.jpg" alt="Battles Family Bible — Births"></div>
</section>

<div class="hist-body">
 <div class="hist-wrap">
  <aside class="hist-side">
    <h3>History Chapters</h3>
    <a class="hs-item hs-all<?= $current ? '' : ' on' ?>" href="history.php"><span class="hs-home">&#8962;</span> All Chapters</a>
    <?php foreach ($CHAPTERS as $c): ?>
      <a class="hs-item<?= $current && $current['slug']===$c['slug'] ? ' on' : '' ?>" href="history.php?ch=<?= e($c['slug']) ?>">
        <span class="hs-num"><?= $c['n'] ?></span><?= e($c['title']) ?></a>
    <?php endforeach; ?>
    <div class="hs-tree"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></div>
    <blockquote class="hs-quote">"Those who do not remember the past are condemned to repeat it."<span>&mdash; George Santayana</span></blockquote>
  </aside>

  <main class="hist-main">
  <?php if (!$current): /* ---------- ALL CHAPTERS ---------- */ ?>
    <?= $STORY_HTML ?>
    <div class="chapters-head">
      <span class="ch-orn">&#10086;</span>
      <h2>The Full Record &mdash; Chapter by Chapter</h2>
      <p>The complete history, chapter by chapter &mdash; the census reports, the family Bible, the schoolhouse and the cemetery, each with its own photographs and records.</p>
    </div>
    <div class="hist-grid">
      <?php foreach ($CHAPTERS as $c): ?>
        <article class="hist-card" id="ch-<?= $c['n'] ?>">
          <?= hist_card_media($c) ?>
          <div class="hc-body">
            <div class="hc-head"><span class="hc-num"><?= $c['n'] ?></span><h3><?= e($c['title']) ?></h3></div>
            <div class="hc-date"><?= e($c['date']) ?></div>
            <p class="hc-ex"><?= e($c['lead']) ?></p>
            <a class="btn2" href="history.php?ch=<?= e($c['slug']) ?>">Read Full Story &rsaquo;</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: /* ---------- SINGLE CHAPTER ---------- */
    $i = array_search($current['slug'], array_column($CHAPTERS, 'slug'));
    $prev = $i > 0 ? $CHAPTERS[$i-1] : null;
    $next = $i < count($CHAPTERS)-1 ? $CHAPTERS[$i+1] : null;
  ?>
    <article class="hist-full">
      <div class="hf-head"><span class="hc-num big"><?= $current['n'] ?></span>
        <div><h2><?= e($current['title']) ?></h2><div class="hc-date"><?= e($current['date']) ?></div></div></div>
      <div class="hf-text">
        <?= $current['body'] /* trusted, authored above */ ?>
      </div>
      <div class="hf-nav">
        <?php if ($prev): ?><a href="history.php?ch=<?= e($prev['slug']) ?>">&lsaquo; <?= e($prev['title']) ?></a><?php else: ?><span></span><?php endif; ?>
        <a href="history.php" class="hf-all">All Chapters</a>
        <?php if ($next): ?><a href="history.php?ch=<?= e($next['slug']) ?>"><?= e($next['title']) ?> &rsaquo;</a><?php else: ?><span></span><?php endif; ?>
      </div>
    </article>
  <?php endif; ?>
  </main>
 </div>
</div>
<?php legacy_footer(); page_foot();
