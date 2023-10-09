<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Plugin\Framework\App\Config\Initial;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\Initial\SchemaLocator;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\Config\Dom;
use Wallee\Payment\Model\Config\DomFactory;

/**
 * Interceptor to dynamically extend the initial configuration with the wallee payment method data.
 */
class Converter
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     *
     * @var DomFactory
     */
    private $domFactory;

    /**
     *
     * @var null|string
     */
    private $schemaFile;

    /**
     *
     * @var ModuleDirReader
     */
    private $moduleReader;

    /**
     *
     * @var DriverPool
     */
    private $driverPool;

    /**
     *
     * @var string
     */
    private $template;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ResourceConnection $resourceConnection
     * @param DomFactory $domFactory
     * @param SchemaLocator $schemaLocator
     * @param ModuleDirReader $moduleReader
     * @param DriverPool $driverPool
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, ResourceConnection $resourceConnection, DomFactory $domFactory,
        SchemaLocator $schemaLocator, ModuleDirReader $moduleReader, DriverPool $driverPool)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->domFactory = $domFactory;
        $this->schemaFile = $schemaLocator->getSchema();
        $this->moduleReader = $moduleReader;
        $this->driverPool = $driverPool;
    }

    /**
     * @param \Magento\Framework\App\Config\Initial\Converter $subject
     * @param mixed $source
     * @return array<mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeConvert(\Magento\Framework\App\Config\Initial\Converter $subject, $source)
    {
        if (! $this->isTableExists()) {
            return [
                $source
            ];
        }

        $configMerger = $this->domFactory->createDom(
            [
                'xml' => Dom::CONFIG_INITIAL_CONTENT,
                'schemaFile' => $this->schemaFile
            ]);
        $configMerger->setDom($source);

        $searchCriteria = $this->searchCriteriaBuilder->addFilter(PaymentMethodConfigurationInterface::STATE,
            [
                PaymentMethodConfiguration::STATE_ACTIVE,
                PaymentMethodConfiguration::STATE_INACTIVE
            ], 'in')->create();

        $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
        foreach ($configurations as $configuration) {
            $configMerger->merge($this->generateXml($configuration));
        }

        return [
            $configMerger->getDom()
        ];
    }

    /**
     * @param PaymentMethodConfigurationInterface $configuration
     * @return array|string|string[]
     */
    private function generateXml(PaymentMethodConfigurationInterface $configuration)
    {
        return \str_replace([
            '{id}',
            '{active}',
            '{title}',
            '{description}',
            '{sortOrder}',
            '{spaceId}'
        ],
            [
                $configuration->getEntityId(),
                $configuration->getState() == PaymentMethodConfiguration::STATE_ACTIVE ? 1 : 0,
                $this->getTranslatedTitle($configuration, LocaleHelper::DEFAULT_LANGUAGE),
                $this->translate($configuration->getDescription(), LocaleHelper::DEFAULT_LANGUAGE),
                $configuration->getSortOrder(),
                $configuration->getSpaceId()
            ], $this->getTemplate());
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        try {
            $this->resourceConnection->getConnection();
        } catch (\Exception $e) {
            return false;
        }

        return $this->resourceConnection->getConnection()->isTableExists(
            $this->resourceConnection->getTableName('wallee_payment_method_configuration'));
    }

    /**
     * Gets the translated title of the payment method configuration.
     *
     * If the title is not set, the configuration's name will be returned instead.
     *
     * @param PaymentMethodConfiguration $configuration
     * @param string $language
     * @return string
     */
    private function getTranslatedTitle(PaymentMethodConfiguration $configuration, $language)
    {
        $translatedTitle = $this->translate($configuration->getTitle(), $language);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $configuration->getConfigurationName();
        }
    }

    /**
     * @param array<mixed> $translatedString
     * @param string $language
     * @return mixed|null
     */
    private function translate($translatedString, $language)
    {
        $language = \str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }

        if (isset($translatedString[LocaleHelper::DEFAULT_LANGUAGE])) {
            return $translatedString[LocaleHelper::DEFAULT_LANGUAGE];
        }

        return null;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getTemplate()
    {
        if ($this->template == null) {
            $templatePath = $this->moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR,
                'Wallee_Payment') . '/config-method-template.xml';
            $this->template = $this->driverPool->getDriver(DriverPool::FILE)->fileGetContents($templatePath);
        }
        return $this->template;
    }
}