<?php
/**
 * PestCalendar — NPMA/USDA-inspired seasonal pest pressure data per region.
 *
 * Keeps the blog (and local SEO) fresh: "what's active in my region right now"
 * powers auto-generated regional posts and the admin "Generate queue" action.
 * Source notes cite NPMA guidance + regional climate (USDA hardiness zones).
 */

declare(strict_types=1);

namespace PPC\Core;

final class PestCalendar
{
    /**
     * pest => region => [startMonth, endMonth, severity, note]
     * Months are 1..12 (1=Jan). Source: NPMA seasonal guidance + regional climate.
     */
    public const CALENDAR = [
        'ants' => [
            'wa' => [3, 11, 'high', 'Carpenter ants swarm in spring; odorous house ants peak summer'],
            'id' => [3, 10, 'high', 'Peak pressure March–October'],
            'or' => [3, 11, 'high', 'Wet west side keeps them active into late fall'],
            'az' => [1, 12, 'high', 'Year-round; harvester ants in summer'],
        ],
        'termites' => [
            'wa' => [4, 10, 'medium', 'Subterranean swarms April–June'],
            'id' => [4, 9, 'medium', 'Swarm season spring'],
            'or' => [4, 10, 'medium', 'Western drywood + subterranean'],
            'az' => [1, 12, 'high', 'Year-round risk; swarm after summer rains'],
        ],
        'mosquitoes' => [
            'wa' => [5, 9, 'high', 'Breeding peaks after June rains'],
            'id' => [5, 9, 'high', 'Peak July–August'],
            'or' => [5, 9, 'medium', 'Valley areas heaviest'],
            'az' => [2, 11, 'high', 'Aedes active in monsoon season July–September'],
        ],
        'rodents' => [
            'wa' => [9, 4, 'high', 'Indoor pressure peaks fall–winter (cold drives them in)'],
            'id' => [9, 4, 'high', 'Fall–winter influx'],
            'or' => [9, 4, 'high', 'Rural fields push field mice in'],
            'az' => [1, 12, 'high', 'Pack rats (woodrats) year-round; roof rats coastal'],
        ],
        'spiders' => [
            'wa' => [3, 10, 'medium', 'Black widow + hobo activity spring–fall'],
            'id' => [3, 10, 'medium', 'Widows peak in late summer'],
            'or' => [3, 10, 'medium', 'Hobo spiders along wet areas'],
            'az' => [1, 12, 'high', 'Black widows year-round; bark scorpions in warm months'],
        ],
        'wasps' => [
            'wa' => [4, 9, 'high', 'Queens build nests spring; aggressive late summer'],
            'id' => [4, 9, 'high', 'Yellowjackets peak August–September'],
            'or' => [4, 9, 'medium', 'Paper wasps common'],
            'az' => [2, 11, 'medium', 'Long warm season'],
        ],
        'fleas-ticks' => [
            'wa' => [4, 9, 'medium', 'Ticks active spring–fall; fleas peak summer'],
            'id' => [4, 9, 'medium', 'Mountain valleys'],
            'or' => [4, 10, 'medium', 'Coastal damp keeps ticks longer'],
            'az' => [2, 10, 'medium', 'Hot dry summers slow fleas, monsoon revives'],
        ],
        'bed-bugs' => [
            'wa' => [1, 12, 'medium', 'Year-round; travel-driven'],
            'id' => [1, 12, 'medium', 'Year-round'],
            'or' => [1, 12, 'medium', 'Year-round'],
            'az' => [1, 12, 'medium', 'Year-round; summer travel spikes'],
        ],
        'cockroaches' => [
            'wa' => [1, 12, 'medium', 'German roach indoors year-round'],
            'id' => [1, 12, 'medium', 'Indoor'],
            'or' => [1, 12, 'medium', 'Indoor; American roach in sewers'],
            'az' => [1, 12, 'high', 'Turkestan roach outdoors year-round'],
        ],
        'scorpions' => [
            'az' => [3, 11, 'high', 'Bark scorpions most active May–October'],
        ],
        'moles-gophers' => [
            'wa' => [2, 11, 'medium', 'Tunneling peaks spring + fall'],
            'id' => [2, 11, 'medium', 'Spring–fall'],
            'or' => [3, 10, 'medium', 'Wet lawns'],
        ],
        'raccoons' => [
            'wa' => [1, 12, 'medium', 'Year-round; attic intrusions late winter'],
            'id' => [1, 12, 'medium', 'Year-round'],
            'or' => [1, 12, 'medium', 'Year-round'],
        ],
    ];

    public const REGION_LABEL = [
        'all' => 'Washington, Idaho, Oregon & Arizona',
        'wa'  => 'Washington',
        'id'  => 'Idaho',
        'or'  => 'Oregon',
        'az'  => 'Arizona',
    ];

    /** [pest => note] for a region in a given month (1..12). */
    public static function activeFor(string $region, int $month = 0): array
    {
        $month = $month === 0 ? (int) date('n') : $month;
        $out = [];
        foreach (self::CALENDAR as $pest => $regions) {
            $entry = $regions[$region] ?? $regions['all'] ?? null;
            if ($entry === null) { continue; }
            [$start, $end, $severity, $note] = $entry;
            $active = ($start <= $end)
                ? ($month >= $start && $month <= $end)
                : ($month >= $start || $month <= $end); // wraps year (rodents Sep–Apr)
            if ($active) {
                $out[$pest] = $note;
            }
        }
        return $out;
    }

    /** One-line summary: "This month in Washington: ants, mosquitoes, ..." */
    public static function monthSummary(string $region, int $month = 0): string
    {
        $month = $month === 0 ? (int) date('n') : $month;
        $label = self::REGION_LABEL[$region] ?? $region;
        $active = array_keys(self::activeFor($region, $month));
        $names = array_map(fn($p) => ucfirst(str_replace('-', ' ', $p)), $active);
        if (!$names) {
            return "Low pest pressure this month in $label.";
        }
        return "Active this month in $label: " . implode(', ', $names) . '.';
    }
}
