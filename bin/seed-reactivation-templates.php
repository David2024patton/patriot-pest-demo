<?php
/**
 * bin/seed-reactivation-templates.php — load the seasonal reactivation library.
 *
 * The reactivation_sends funnel schema is complete but the engine has zero
 * content. This seeds the seasonal pest calendar (Hunter's market research):
 * one template per season, each targeting the dominant pest for that window.
 * Merge tags used by the engine: {{name}}, {{city}}, {{pest}}, {{season}},
 * {{unsubscribe_url}}.
 *
 * Idempotent: templates are matched by name; re-running only inserts missing
 * rows. SMS bodies are validated <= 160 chars (single-segment Twilio).
 *
 *   php bin/seed-reactivation-templates.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();
$templates = [
    [
        'name'      => 'Spring Defense: Ant & Termite Swarm',
        'season'    => 'spring',
        'pest_type' => 'ants',
        'states'    => '["WA","ID","OR","AZ"]',
        'channel'   => 'both',
        'subject'   => '{{name}}, spring swarms are starting in {{city}}',
        'body_html' => '<p>Hi {{name}},</p><p>Spring rains in {{city}} trigger {{pest}} swarms as colonies wake up and look for food and shelter. A single pre-treatment now stops the colony before it establishes a trail into your home.</p><p>Our <strong>{{season}} defense</strong> covers the perimeter, seals entry points, and baits the colony at the source. One visit protects your home through the whole season.</p><p>Call <a href="tel:+15094715767">(509) 471-5767</a> or reply to this email to book your spring treatment.</p><p>— Patriot Pest Control</p><p style="font-size:12px;color:#777"><a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
        'body_sms'  => '{{name}}, spring ant & termite swarms are starting in {{city}}. One treatment protects your home all season. Call (509) 471-5767. {{unsubscribe_url}}',
    ],
    [
        'name'      => 'Summer Mosquito Defense',
        'season'    => 'summer',
        'pest_type' => 'mosquitoes',
        'states'    => '["WA","ID","OR","AZ"]',
        'channel'   => 'both',
        'subject'   => '{{name}}, take your {{city}} yard back from mosquitoes',
        'body_html' => '<p>Hi {{name}},</p><p>Mosquito season peaks in {{city}} right now — and it only takes one untreated yard to ruin every evening outside.</p><p>Our <strong>{{season}} barrier treatment</strong> targets {{pest}} breeding and resting sites, knocking down the population for weeks at a time. It is pet-safe once dry and includes re-treatments between scheduled visits.</p><p>Call <a href="tel:+15094715767">(509) 471-5767</a> or reply to book your yard treatment.</p><p>— Patriot Pest Control</p><p style="font-size:12px;color:#777"><a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
        'body_sms'  => '{{name}}, mosquitoes peak in {{city}} right now. Our summer barrier knocks them out for weeks, pet-safe. Call (509) 471-5767. {{unsubscribe_url}}',
    ],
    [
        'name'      => 'Fall Rodent Exclusion',
        'season'    => 'fall',
        'pest_type' => 'rodents',
        'states'    => '["WA","ID","OR","AZ"]',
        'channel'   => 'both',
        'subject'   => '{{name}}, mice are looking for a warm home in {{city}}',
        'body_html' => '<p>Hi {{name}},</p><p>As temperatures drop in {{city}}, {{pest}} start moving indoors — a mouse can squeeze through a gap the size of a dime.</p><p>Our <strong>{{season}} exclusion</strong> seals entry points, traps existing invaders, and places monitored bait stations so the problem never becomes an infestation. Fall is the single best time to act, before winter drives them all inside.</p><p>Call <a href="tel:+15094715767">(509) 471-5767</a> or reply to schedule your exclusion.</p><p>— Patriot Pest Control</p><p style="font-size:12px;color:#777"><a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
        'body_sms'  => '{{name}}, as temps drop in {{city}}, mice move indoors. Fall exclusion seals them out before winter. Call (509) 471-5767. {{unsubscribe_url}}',
    ],
    [
        'name'      => 'Winter Pest Shield',
        'season'    => 'winter',
        'pest_type' => 'rodents',
        'states'    => '["WA","ID","OR","AZ"]',
        'channel'   => 'both',
        'subject'   => '{{name}}, winter pests are seeking shelter in {{city}}',
        'body_html' => '<p>Hi {{name}},</p><p>Winter drives {{pest}} and other cold-sensitive pests to seek shelter — and your {{city}} home is the warmest option on the block.</p><p>Our <strong>{{season}} shield</strong> keeps the barrier fresh through the coldest months, including rodent monitoring and exclusion maintenance, so you never wake up to scratching in the walls.</p><p>Call <a href="tel:+15094715767">(509) 471-5767</a> or reply to renew your protection.</p><p>— Patriot Pest Control</p><p style="font-size:12px;color:#777"><a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
        'body_sms'  => '{{name}}, rodents & scorpions seek warmth this winter. Protect your {{city}} home with our winter barrier. Call (509) 471-5767. {{unsubscribe_url}}',
    ],
];

$inserted = 0;
foreach ($templates as $t) {
    if ($db->fetch('SELECT id FROM reactivation_templates WHERE name = ?', [$t['name']])) {
        continue;
    }
    if (strlen($t['body_sms']) > 160) {
        fwrite(STDERR, "SKIP {$t['name']}: SMS body " . strlen($t['body_sms']) . " chars > 160\n");
        continue;
    }
    $db->insert('reactivation_templates', [
        'name'       => $t['name'],
        'subject'    => $t['subject'],
        'body_html'  => $t['body_html'],
        'body_sms'   => $t['body_sms'],
        'pest_type'  => $t['pest_type'],
        'season'     => $t['season'],
        'states'     => $t['states'],
        'channel'    => $t['channel'],
        'active'     => 1,
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);
    $inserted++;
    printf("  + %-38s %-8s pest=%s  sms=%d chars\n", $t['name'], $t['season'], $t['pest_type'], strlen($t['body_sms']));
}

$total = (int) $db->scalar('SELECT COUNT(*) FROM reactivation_templates');
echo "Reactivation templates seeded: {$inserted} new (total now {$total})\n";

// Validation: field-appropriate merge-tag contract.
//   subject:  {{name}} (personalization), {{city}} optional
//   body_html: all five tags (full email)
//   body_sms: {{name}} + {{unsubscribe_url}} required, {{city}}/{{pest}}/{{season}} optional (160-char budget)
$contracts = [
    'subject'   => ['{{name}}'],
    'body_html' => ['{{name}}', '{{city}}', '{{pest}}', '{{season}}', '{{unsubscribe_url}}'],
    'body_sms'  => ['{{name}}', '{{unsubscribe_url}}'],
];
$bad = 0;
foreach ($db->fetchAll('SELECT name, body_sms, body_html, subject FROM reactivation_templates') as $r) {
    foreach ($contracts as $col => $tags) {
        foreach ($tags as $tag) {
            if (!str_contains((string) $r[$col], $tag)) {
                fwrite(STDERR, "WARN {$r['name']}: missing {$tag} in {$col}\n");
                $bad++;
            }
        }
    }
}
echo $bad ? "Merge-tag validation FAILED ({$bad} issues).\n" : "Merge-tag validation passed (all templates meet the contract).\n";
