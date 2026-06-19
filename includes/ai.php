<?php
/**
 * includes/ai.php — AgriConnect AI Advisor engine.
 *
 * Exposes agri_market_advice() which returns smart pricing + demand guidance
 * for a farmer. Uses Claude (Anthropic PHP SDK) when an API key is configured,
 * and falls back to a transparent rule-based engine otherwise — so the feature
 * always returns something useful, even with no key.
 *
 * API key resolution order:
 *   1. ANTHROPIC_API_KEY environment variable
 *   2. includes/ai_config.php  ->  return ['api_key' => 'sk-ant-...'];
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Anthropic\Client;

if (!function_exists('agri_ai_key')) {
    function agri_ai_key(): ?string {
        $env = getenv('ANTHROPIC_API_KEY');
        if ($env) return trim($env);
        $cfg = __DIR__ . '/ai_config.php';
        if (is_file($cfg)) {
            $data = include $cfg;
            if (is_array($data) && !empty($data['api_key'])) return trim($data['api_key']);
        }
        return null;
    }
}

/**
 * @param array $products       [['title','quantity_kg','price_per_kg','harvest_date'], ...]
 * @param array $market_prices  [['crop_name','district_name','avg_price','trend'], ...]
 * @param array $demand         [['title','orders_30d'], ...]
 * @param string $district      Farmer's district
 * @return array  ['source'=>'ai'|'heuristic', 'overall_summary'=>..., 'products'=>[...], 'demand_hotspots'=>[...]]
 */
function agri_market_advice(array $products, array $market_prices, array $demand, string $district): array {
    $key = agri_ai_key();
    if ($key) {
        try {
            return agri_market_advice_ai($key, $products, $market_prices, $demand, $district);
        } catch (\Throwable $e) {
            // Fall through to heuristic; attach a note for debugging.
            $out = agri_market_advice_heuristic($products, $market_prices, $demand, $district);
            $out['note'] = 'AI unavailable (' . $e->getMessage() . ') — showing rule-based advice.';
            return $out;
        }
    }
    return agri_market_advice_heuristic($products, $market_prices, $demand, $district);
}

function agri_market_advice_ai(string $key, array $products, array $market_prices, array $demand, string $district): array {
    $client = new Client(apiKey: $key);

    $context = json_encode([
        'farmer_district' => $district,
        'currency'        => 'RWF',
        'listed_products' => $products,
        'market_prices'   => $market_prices,
        'platform_demand_last_30_days' => $demand,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $prompt = <<<PROMPT
You are AgriConnect's market advisor for Rwandan farmers. Using the JSON data below,
give concrete, practical guidance that helps this farmer earn more and reduce
post-harvest losses. Compare each listed product's price to the relevant market
price and trend, and factor in platform demand and how perishable the crop is
(older harvest_date = sell sooner). Be specific and encouraging but honest.
Prices are in RWF per kg. Keep every "reason" to one short sentence.

DATA:
$context
PROMPT;

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'overall_summary' => ['type' => 'string'],
            'products' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'current_price_per_kg' => ['type' => 'integer'],
                        'recommended_price_per_kg' => ['type' => 'integer'],
                        'verdict' => ['type' => 'string', 'enum' => ['sell_now', 'hold', 'raise_price', 'lower_price']],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['title', 'current_price_per_kg', 'recommended_price_per_kg', 'verdict', 'reason'],
                ],
            ],
            'demand_hotspots' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'district' => ['type' => 'string'],
                        'crop' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                    ],
                    'required' => ['district', 'crop', 'note'],
                ],
            ],
        ],
        'required' => ['overall_summary', 'products', 'demand_hotspots'],
    ];

    $message = $client->messages->create(
        model: 'claude-opus-4-8',
        maxTokens: 8000,
        thinking: ['type' => 'adaptive'],
        messages: [['role' => 'user', 'content' => $prompt]],
        outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
    );

    $json = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') { $json .= $block->text; }
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new \RuntimeException('Could not parse AI response.');
    }
    $data['source'] = 'ai';
    return $data;
}

/**
 * Transparent rule-based fallback — no external calls. Matches each product to a
 * market price by fuzzy crop-name match and reasons about price vs trend + demand.
 */
function agri_market_advice_heuristic(array $products, array $market_prices, array $demand, string $district): array {
    $demand_by_title = [];
    foreach ($demand as $d) { $demand_by_title[strtolower($d['title'])] = (int)$d['orders_30d']; }

    $find_market = function (string $title) use ($market_prices) {
        $t = strtolower($title);
        foreach ($market_prices as $m) {
            $crop = strtolower($m['crop_name']);
            if (str_contains($t, $crop) || str_contains($crop, explode(' ', $t)[count(explode(' ', $t)) - 1])) {
                return $m;
            }
        }
        return null;
    };

    $product_advice = [];
    foreach ($products as $p) {
        $cur = (int)$p['price_per_kg'];
        $m = $find_market($p['title']);
        $rec = $cur;
        $verdict = 'hold';
        $reason = 'No clear market signal — keep monitoring prices.';

        $days_old = isset($p['harvest_date']) && $p['harvest_date']
            ? (int)((time() - strtotime($p['harvest_date'])) / 86400) : 0;
        $orders = $demand_by_title[strtolower($p['title'])] ?? 0;

        if ($m) {
            $avg = (int)$m['avg_price'];
            $trend = $m['trend'];
            if ($cur < $avg * 0.95 && $trend !== 'DOWN') {
                $verdict = 'raise_price';
                $rec = (int)round(($cur + $avg) / 2);
                $reason = "You're below the {$avg} RWF market average and prices are {$trend} — raise toward {$rec}.";
            } elseif ($cur > $avg * 1.1) {
                $verdict = 'lower_price';
                $rec = $avg;
                $reason = "You're well above the {$avg} RWF average — trim to stay competitive.";
            } elseif ($trend === 'DOWN') {
                $verdict = 'sell_now';
                $reason = "Market trend is falling — sell soon before prices drop further.";
            } else {
                $reason = "Your price is in line with the {$avg} RWF market average.";
            }
        }

        if ($days_old > 5) {
            $verdict = 'sell_now';
            $reason = "Harvested ~{$days_old} days ago — prioritise selling to avoid spoilage.";
        }
        if ($orders >= 3 && $verdict !== 'sell_now') {
            $verdict = 'raise_price';
            $reason = "Strong demand ({$orders} orders in 30 days) — you can push the price a little higher.";
        }

        $product_advice[] = [
            'title' => $p['title'],
            'current_price_per_kg' => $cur,
            'recommended_price_per_kg' => $rec,
            'verdict' => $verdict,
            'reason' => $reason,
        ];
    }

    $hotspots = [];
    foreach ($market_prices as $m) {
        if ($m['trend'] === 'UP') {
            $hotspots[] = [
                'district' => $m['district_name'],
                'crop' => $m['crop_name'],
                'note' => "Prices rising in {$m['district_name']} — good place to sell {$m['crop_name']}.",
            ];
        }
    }
    $hotspots = array_slice($hotspots, 0, 4);

    $summary = count($product_advice)
        ? 'Based on current market prices and demand, here is where you can adjust to earn more and cut losses.'
        : 'List some produce to get personalised pricing and demand advice.';

    return [
        'source' => 'heuristic',
        'overall_summary' => $summary,
        'products' => $product_advice,
        'demand_hotspots' => $hotspots,
    ];
}
