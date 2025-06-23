<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Catalog\Article;
use Evoliz\Client\Repository\Catalog\ArticleRepository;

class EvolizArticle
{
    /**
     * @throws ResourceException
     * @throws Exception
     */
    public static function findOrCreate(Config $config, WC_Product $product): Article
    {
        // Check if article already exists
        $articleRepository = new ArticleRepository($config);
        $articles = $articleRepository->list(['reference' => $product->get_sku()]);

        if (!empty($articles->data)) {
            writeLog('[ Article : ' . $product->get_name() . ' ] The Article has been successfully retrieved (' . $articles->data[0]->articleid . ').');
            return new Article((array) $articles->data[0]);
        }

        // Article doesn't exist, we create it
        $articleData = [
            'reference' => $product->get_sku(),
            'designation' => $product->get_name(),
            'quantity' => 1,
            'weight' => $product->get_weight() ? (float) $product->get_weight() : null,
            'unit_price' => (float) $product->get_price(),
            'vat_rate' => self::getTaxRate($product),
            'ttc' => get_option('woocommerce_prices_include_tax') === 'yes',
        ];

        $article = $articleRepository->create(new Article($articleData))->createFromResponse();
        writeLog('[ Article : ' . $product->get_name() . ' ] The Article has been successfully created (' . $article->articleid . ').');

        return $article;
    }

    private static function getTaxRate(WC_Product $product): ?float
    {
        $taxClass = $product->get_tax_class();
        $taxRates = WC_Tax::get_rates($taxClass);

        if (!empty($taxRates)) {
            $rate = reset($taxRates);
            return floatval($rate['rate']);
        }

        return null;
    }
}
