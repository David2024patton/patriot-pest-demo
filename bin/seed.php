<?php
/**
 * bin/seed.php — populate the database with starter data.
 *
 * Run once (safe to re-run; uses INSERT OR IGNORE / upsert logic):
 *   php bin/seed.php
 *
 * Seeds:
 *   - the full pest photo library (25 pests, real photos),
 *   - staff accounts (from the existing system, so login works immediately),
 *   - a few sample customers (to test the passwordless login),
 *   - a few sample blog posts (to demonstrate the unified blog template).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();

/* ---------- Pest photo library (all 25 pests Patriot treats) ---------- */
$pests = [
    // slug, name, scientific, filename, category, threat, description
    ['ants','Ants','Camponotus spp.','ants.jpg','insect',72,'Carpenter ants, sugar ants, and odorous invaders — colonies dismantled at the source.'],
    ['spiders','Spiders','Latrodectus spp.','spiders.jpg','insect',64,'Black widows, hobo spiders, and more. Web removal plus entry-point lockdown.'],
    ['rodents','Rodents','Rattus norvegicus','rodents.jpg','rodent',81,'Mice and rats excluded, trapped, and sealed out — droppings and damage remediated.'],
    ['bed-bugs','Bed Bugs','Cimex lectularius','bed-bugs.jpg','insect',93,'Complete elimination with follow-up verification. Discreet, thorough, guaranteed.'],
    ['termites','Termites','Reticulitermes spp.','termites.jpg','insect',96,'Structural damage stopped cold with inspection, treatment, and monitoring.'],
    ['mosquitoes','Mosquitoes','Aedes aegypti','mosquitoes.jpg','insect',70,'Seasonal barrier treatments that take back your yard from peak spring through fall.'],
    ['wasps','Wasps & Hornets','Polistes spp.','wasps.jpg','insect',78,'Safe nest removal by suited technicians — eaves, attics, and ground nests.'],
    ['cockroaches','Cockroaches','Periplaneta americana','cockroaches.jpg','insect',75,'Gel-bait and IGR programs that break the breeding cycle for good.'],
    ['fleas-ticks','Fleas & Ticks','Ctenocephalides spp.','fleas-ticks.jpg','insect',66,'Indoor and perimeter treatment that's tough on pests, safe for pets.'],
    ['silverfish','Silverfish','Lepisma saccharina','silverfish.jpg','insect',40,'Moisture-loving invaders that damage books, clothing, and wallpaper.'],
    ['pantry-pests','Pantry Pests','Plodia interpunctella','pantry-pests.jpg','insect',45,'Indian meal moths, flour beetles, and weevils — kitchen invaders eliminated.'],
    ['bees','Bees','Apis mellifera','bees.jpg','insect',60,'Humane honeybee, carpenter bee & Africanized bee removal, prioritizing relocation.'],
    ['squirrels','Squirrels','Sciurus carolinensis','squirrels.jpg','wildlife',50,'Humane removal from attics, walls & chimneys with entry-point sealing.'],
    ['raccoons','Raccoons','Procyon lotor','raccoons.jpg','wildlife',55,'Safe removal & exclusion using humane trapping and one-way doors.'],
    ['bats','Bats','Myotis lucifugus','bats.jpg','wildlife',58,'Professional one-way door exclusion — bats leave safely and can't return.'],
    ['scorpions','Scorpions','Centruroides spp.','scorpions.jpg','insect',85,'Bark scorpions & desert hairy scorpions — UV detection and targeted treatments.'],
    ['crickets','Crickets','Acheta domesticus','crickets.jpg','insect',35,'Camel, Jerusalem & field crickets — eliminate noisy infestations.'],
    ['pack-rats','Pack Rats','Neotoma spp.','pack-rats.jpg','rodent',62,'Desert woodrats that nest in attics, garages & vehicles.'],
    ['box-elder-bugs','Box Elder Bugs','Boisea trivittata','box-elder-bugs.jpg','insect',30,'Fall invaders that cluster on sunny walls — excluded before they get inside.'],
    ['stink-bugs','Stink Bugs','Halyomorpha halys','stink-bugs.jpg','insect',38,'Seasonal invaders that overwinter in walls — sealed out and removed.'],
    ['fruit-flies','Flies','Calliphora spp.','fruit-flies.jpg','insect',33,'Fruit flies, drain flies & house flies — breeding sources eliminated.'],
    ['gophers','Gophers','Thomomys talpoides','gophers.jpg','rodent',48,'Lawn-damaging burrowers — trapped and excluded humanely.'],
    ['moles','Moles','Scapanus latimanus','moles.jpg','rodent',42,'Tunneling insectivores that wreck lawns — controlled at the source.'],
    ['hornets','Hornets','Vespa crabro','hornets.jpg','insect',80,'Aggressive stinging insects — nests removed safely by suited technicians.'],
    ['yellow-jackets','Yellow Jackets','Vespula germanica','yellow-jackets.jpg','insect',77,'Ground-nesting stinging pests — located, treated, and prevented.'],
];

$count = 0;
foreach ($pests as $i => [$slug,$name,$sci,$file,$cat,$threat,$desc]) {
    $exists = $db->fetch('SELECT id FROM pest_photos WHERE slug = ?', [$slug]);
    if ($exists) { continue; }
    $db->insert('pest_photos', [
        'slug'=>$slug,'name'=>$name,'scientific_name'=>$sci,'filename'=>$file,
        'description'=>$desc,'category'=>$cat,'threat_level'=>$threat,'sort_order'=>$i,
        'created_at'=>date('Y-m-d H:i:s'),
    ]);
    $count++;
}
echo "Pest photos seeded: $count (total now " . $db->scalar('SELECT COUNT(*) FROM pest_photos') . ")\n";

/* ---------- Staff (from the existing system — login works immediately) ---------- */
$staff = [
    ['ppc_info@patriotpest.pro','Patriot Pest Control','admin'],
    ['mrose@patriotpest.pro','M. Rose','admin'],
    ['jordan@patriotpest.pro','Jordan','staff'],
    ['david.richard.patton@gmail.com','David Patton','admin'],
    ['david@itak.net','David Patton','admin'],
];
$sc = 0;
foreach ($staff as [$email,$name,$role]) {
    if ($db->fetch('SELECT id FROM staff WHERE email = ?', [$email])) { continue; }
    $db->insert('staff', ['email'=>$email,'name'=>$name,'role'=>$role,'active'=>1,'created_at'=>date('Y-m-d H:i:s')]);
    $sc++;
}
echo "Staff seeded: $sc\n";

/* ---------- Sample customers (to test passwordless login) ---------- */
$customers = [
    ['1001','Jane Smith','jane@example.com','+15095550101','1234 W Main Ave','Spokane','WA','99201','active'],
    ['1002','Bob Jones','bob@example.com','+15095550102','567 E Pine St','Coeur d\'Alene','ID','83814','active'],
    ['1003','Carol White','carol@example.com','+16025550103','890 N Desert Ln','Phoenix','AZ','85004','cancelled'],
];
$cc = 0;
foreach ($customers as [$fr,$name,$email,$phone,$addr,$city,$st,$zip,$status]) {
    if ($db->fetch('SELECT id FROM customers WHERE email = ?', [$email])) { continue; }
    $db->insert('customers', [
        'fr_id'=>$fr,'district'=>($st==='AZ'?'az':'wa'),'name'=>$name,'email'=>$email,'phone'=>$phone,
        'account_number'=>$fr,'address'=>$addr,'city'=>$city,'state'=>$st,'zip'=>$zip,'status'=>$status,
        'is_no_call'=>($status==='cancelled'?1:0),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
    ]);
    $cc++;
}
echo "Customers seeded: $cc\n";

/* ---------- Sample blog posts (demonstrate the unified template + photo picker) ---------- */
$antId  = $db->scalar("SELECT id FROM pest_photos WHERE slug='ants'");
$spidId = $db->scalar("SELECT id FROM pest_photos WHERE slug='spiders'");
$rodId  = $db->scalar("SELECT id FROM pest_photos WHERE slug='rodents'");
$now = date('Y-m-d H:i:s');
$posts = [
    ['why-ants-invade-in-spring','Why Ants Invade in Spring (and How to Stop Them)','Ants scout for food and water as temperatures rise. Here's how to cut them off before they establish a colony.',$antId,'spring','ants',
     '<p>As soil temperatures climb, ant colonies send out scouts looking for food and water. A single scout that finds a reliable source lays a pheromone trail — and within days you have a highway into your kitchen.</p><h3>What to do</h3><ul><li>Seal cracks around the foundation and windows.</li><li>Store food in airtight containers.</li><li>Wipe down counters to remove scent trails.</li><li>Use baiting (not just sprays) so the colony is eliminated at the source.</li></ul>'],
    ['spiders-fall-guide','Why Spiders Come Inside During Fall','Cooler nights drive spiders indoors. Learn why and how to keep them out.',$spidId,'fall','spiders',
     '<p>When nights cool, spiders follow their prey indoors. Most are harmless, but black widows and hobo spiders warrant attention.</p><h3>Prevention</h3><ul><li>Remove webs and egg sacs from eaves and corners.</li><li>Seal entry points around doors and windows.</li><li>Reduce outdoor lighting that attracts prey insects.</li></ul>'],
    ['rodent-proof-your-home','How to Rodent-Proof Your Home','Mice can squeeze through a gap the size of a dime. Here's a complete exclusion walkthrough.',$rodId,'winter','rodents',
     '<p>Rodents seek warmth and food as winter approaches. Exclusion is the only permanent fix.</p><h3>Checklist</h3><ul><li>Seal gaps larger than 1/4 inch around pipes and vents.</li><li>Store food and pet food in sealed containers.</li><li>Keep garbage in tightly sealed bins.</li><li>Trim vegetation away from the foundation.</li></ul>'],
];
$pc = 0;
foreach ($posts as [$slug,$title,$excerpt,$photo,$season,$cat,$body]) {
    if ($db->fetch('SELECT id FROM posts WHERE slug = ?', [$slug])) { continue; }
    $db->insert('posts', [
        'slug'=>$slug,'title'=>$title,'excerpt'=>$excerpt,'body_html'=>$body,'pest_photo_id'=>$photo,
        'season'=>$season,'pest_category'=>$cat,'status'=>'published','author'=>'Skyler Rose',
        'published_at'=>$now,'date_modified'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $pc++;
}
echo "Posts seeded: $pc\n";

echo "\nSeed complete.\n";
echo "  → Sign in at /login (one page for everyone; routed by role):\n";
echo "      admin    : david@itak.net            -> /admin (CMS)\n";
echo "      staff    : jordan@patriotpest.pro    -> /staff-dashboard\n";
echo "      customer : jane@example.com (or 1001 / +15095550101) -> /customer-dashboard\n";
echo "  → In dev, the emailed code is logged to storage/logs/mail-*.log\n";
