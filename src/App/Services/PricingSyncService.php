<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Kroger\DiscountHistoryApi;
use App\Services\Kroger\NationalPricingApi;
use App\Services\Kroger\PriceHistoryApi;

final class PricingSyncService
{
    public function __construct(
        private readonly PriceHistoryApi $priceHistoryApi,
        private readonly NationalPricingApi $nationalPricingApi,
        private readonly DiscountHistoryApi $discountHistoryApi,
    ) {
    }

    public function sync(string $upc, string $locationId): array
    {
        return [
            'priceHistory' => $this->priceHistoryApi->byUpc($upc, $locationId),
            'nationalPricing' => $this->nationalPricingApi->byUpc($upc),
            'discountHistory' => $this->discountHistoryApi->byUpc($upc, $locationId),
        ];
    }
}
