<?php

declare(strict_types=1);

namespace Northvia\Modules\Products\Models;

use Carbon\Carbon;

/**
 * Product model
 */
class Product
{
    public int $id;
    public ?int $vendor_id;
    public int $category_id;
    public ?int $brand_id;
    public string $name;
    public string $slug;
    public ?string $short_description;
    public ?string $description;
    public string $sku;
    public ?float $weight;
    public ?array $dimensions;
    public float $price;
    public ?float $compare_price;
    public ?float $cost_price;
    public bool $track_inventory;
    public int $inventory_quantity;
    public int $low_stock_threshold;
    public bool $allow_backorders;
    public bool $requires_shipping;
    public bool $is_digital;
    public string $status;
    public bool $featured;
    public ?string $meta_title;
    public ?string $meta_description;
    public ?string $search_keywords;
    public float $rating;
    public int $total_reviews;
    public int $total_sales;
    public Carbon $created_at;
    public Carbon $updated_at;

    // Additional properties for joined data
    public ?string $category_name = null;
    public ?string $brand_name = null;
    public ?string $vendor_name = null;
    public ?array $images = null;
    public ?array $variants = null;

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * Create Product instance from array data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Fill model with data
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                // Handle special cases for date fields
                if (in_array($key, ['created_at', 'updated_at'])) {
                    $this->$key = $value ? Carbon::parse($value) : Carbon::now();
                } elseif ($key === 'dimensions') {
                    $this->$key = is_string($value) ? json_decode($value, true) : $value;
                } elseif (in_array($key, ['track_inventory', 'allow_backorders', 'requires_shipping', 'is_digital', 'featured'])) {
                    $this->$key = (bool) $value;
                } elseif (in_array($key, ['price', 'compare_price', 'cost_price', 'weight', 'rating'])) {
                    $this->$key = $value !== null ? (float) $value : null;
                } elseif (in_array($key, ['inventory_quantity', 'low_stock_threshold', 'total_reviews', 'total_sales'])) {
                    $this->$key = (int) $value;
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $data = [];
        
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof Carbon) {
                $data[$key] = $value->toISOString();
            } elseif ($key === 'dimensions' && is_array($value)) {
                $data[$key] = $value;
            } else {
                $data[$key] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Check if product is available for purchase
     */
    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->track_inventory && $this->inventory_quantity <= 0 && !$this->allow_backorders) {
            return false;
        }

        return true;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        return $this->inventory_quantity > 0;
    }

    /**
     * Check if product is low in stock
     */
    public function isLowStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }

        return $this->inventory_quantity <= $this->low_stock_threshold;
    }

    /**
     * Get stock status
     */
    public function getStockStatus(): string
    {
        if (!$this->track_inventory) {
            return 'in_stock';
        }

        if ($this->inventory_quantity <= 0) {
            return $this->allow_backorders ? 'backorder' : 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Calculate discount percentage
     */
    public function getDiscountPercentage(): ?float
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 2);
    }

    /**
     * Calculate savings amount
     */
    public function getSavingsAmount(): ?float
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return $this->compare_price - $this->price;
    }

    /**
     * Get effective price (considering sales/discounts)
     */
    public function getEffectivePrice(): float
    {
        // This could be extended to consider time-based sales, coupon discounts, etc.
        return $this->price;
    }

    /**
     * Check if product is on sale
     */
    public function isOnSale(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(string $currency = 'NGN'): string
    {
        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format($this->price, 2);
    }

    /**
     * Get formatted compare price
     */
    public function getFormattedComparePrice(string $currency = 'NGN'): ?string
    {
        if (!$this->compare_price) {
            return null;
        }

        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format($this->compare_price, 2);
    }

    /**
     * Get rating stars (1-5 scale)
     */
    public function getRatingStars(): int
    {
        return (int) round($this->rating);
    }

    /**
     * Check if product has reviews
     */
    public function hasReviews(): bool
    {
        return $this->total_reviews > 0;
    }

    /**
     * Get product URL slug
     */
    public function getUrl(): string
    {
        return "/products/{$this->slug}";
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImage(): ?string
    {
        if ($this->images && is_array($this->images)) {
            foreach ($this->images as $image) {
                if ($image['is_primary'] ?? false) {
                    return $image['image_url'];
                }
            }
            // Return first image if no primary is set
            return $this->images[0]['image_url'] ?? null;
        }

        return null;
    }

    /**
     * Get all image URLs
     */
    public function getImageUrls(): array
    {
        if ($this->images && is_array($this->images)) {
            return array_column($this->images, 'image_url');
        }

        return [];
    }

    /**
     * Check if product is digital
     */
    public function isDigitalProduct(): bool
    {
        return $this->is_digital;
    }

    /**
     * Check if product requires shipping
     */
    public function requiresShipping(): bool
    {
        return $this->requires_shipping && !$this->is_digital;
    }

    /**
     * Get estimated shipping weight
     */
    public function getShippingWeight(): float
    {
        return $this->weight ?? 0.5; // Default 0.5kg if not specified
    }

    /**
     * Get product dimensions for shipping
     */
    public function getShippingDimensions(): array
    {
        if ($this->dimensions && is_array($this->dimensions)) {
            return $this->dimensions;
        }

        // Default dimensions if not specified
        return [
            'length' => 10.0,
            'width' => 10.0,
            'height' => 5.0,
            'unit' => 'cm'
        ];
    }

    /**
     * Get SEO meta data
     */
    public function getSeoMeta(): array
    {
        return [
            'title' => $this->meta_title ?: $this->name,
            'description' => $this->meta_description ?: $this->short_description,
            'keywords' => $this->search_keywords,
            'canonical_url' => $this->getUrl()
        ];
    }

    /**
     * Get product statistics
     */
    public function getStats(): array
    {
        return [
            'total_sales' => $this->total_sales,
            'total_reviews' => $this->total_reviews,
            'average_rating' => $this->rating,
            'rating_stars' => $this->getRatingStars(),
            'stock_status' => $this->getStockStatus(),
            'is_featured' => $this->featured,
            'is_on_sale' => $this->isOnSale(),
            'discount_percentage' => $this->getDiscountPercentage()
        ];
    }

    /**
     * Get currency symbol
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];

        return $symbols[$currency] ?? $currency . ' ';
    }
}