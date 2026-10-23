<?php

/**
 * Select a token price pair for an entitlement decision.
 *
 * Twenty-four-hour volume is descriptive only: it is not market depth and can
 * be cheaply inflated. The pair with the greatest actual USD liquidity wins.
 */
function zt_token_gate_select_liquid_pair(array $pairs, string $mint): ?array {
    $best = null;
    foreach ($pairs as $pair) {
        if (!is_array($pair)) {
            continue;
        }
        if (strtolower((string) ($pair['chainId'] ?? '')) !== 'solana') {
            continue;
        }
        if (strcasecmp((string) ($pair['baseToken']['address'] ?? ''), $mint) !== 0) {
            continue;
        }

        $price = (float) ($pair['priceUsd'] ?? 0);
        $liquidity = (float) ($pair['liquidity']['usd'] ?? 0);
        if ($price <= 0 || $liquidity <= 0) {
            continue;
        }
        $volume_24h = (float) ($pair['volume']['h24'] ?? 0);
        if ($best === null || $liquidity > $best['liquidity_usd']) {
            $best = [
                'ok' => true,
                'price_usd' => $price,
                'liquidity_usd' => $liquidity,
                'volume_24h_usd' => $volume_24h,
                'source' => 'dexscreener',
                'pair' => (string) ($pair['pairAddress'] ?? ''),
                'dex' => (string) ($pair['dexId'] ?? ''),
            ];
        }
    }

    return $best;
}

