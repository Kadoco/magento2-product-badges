<?php
declare(strict_types=1);

namespace Kadoco\Badge\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Model\StoreManagerInterface;
use Kadoco\Checkout\ViewModel\CheckoutConfigProvider;
use Kadoco\News\Model\NewsConfigProvider;

class GetGalleryBadgeInformation implements ArgumentInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    /**
     * @var CheckoutConfigProvider
     */
    private CheckoutConfigProvider $checkoutConfigProvider;
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    /**
     * @var NewsConfigProvider
     */
    private NewsConfigProvider $newsConfigProvider;

    public function __construct(
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        CheckoutConfigProvider $checkoutConfigProvider,
        NewsConfigProvider $newsConfigProvider,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->checkoutConfigProvider = $checkoutConfigProvider;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->newsConfigProvider = $newsConfigProvider;
    }

    public function getProduct(int $productId): ?ProductInterface
    {
        try {
            return $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
        }

        return null;
    }

    public function isNews(int $productId): bool
    {
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }

        if (!$this->newsConfigProvider->getIsActive()) {
            return false;
        }
        $categoryId = $this->newsConfigProvider->getCategoryId();
        $categoryIds = $product->getCategoryIds();
        if (!in_array($categoryId, $categoryIds)) {
            return false;
        }

        return true;
    }

    public function getCurrentProduct()
    {
        if ('catalog_product_view' !== $this->request->getFullActionName()) {
            return false;
        }

        $params = $this->request->getParams();
        if (isset($params['id'])) {
            $productId = $params['id'];

            try {
                return $this->productRepository->getById($productId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return false;
            }
        }

        return false;
    }

    public function isFreeDelivery(int $productId): bool
    {
        return false;
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }

        return ((int)$product->getFinalPrice()) >= $this->checkoutConfigProvider->getFreeDeliveryLimit();
    }

    public function isTierPriced(int $productId): bool
    {
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }

        return (bool)count($product->getTierPrice());
    }

    public function isInStock(int $productId):bool
    {
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }

        return (bool)$product->isSaleable();
    }


    private function getWebsiteStockId():int
    {
        $code = $this->storeManager->getWebsite()->getCode();
        $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $code);

        return (int) $stock->getStockId();
    }

    private function getProductSalableQty(string $sku):float
    {
        return (float) $this->getProductSalableQty->execute($sku, $this->getWebsiteStockId());
    }

    public function getDiscountPercentage(int $productId):int
    {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0;
        }

        $regular = $product->getPriceInfo()->getPrices()->get('regular_price');
        if (!$regular) {
            return 0;
        }
        $regularPrice = $regular->getValue();
        $finalPrice = $product->getFinalPrice();
        if ($finalPrice >= $regularPrice) {
            return 0;
        }

        return (int) round(100 *(1-$finalPrice/$regularPrice), 0);
    }
}
