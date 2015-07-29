<?php

class Devhh_Topproducts_Model_General
{

    /** @var  Magento_Db_Adapter_Pdo_Mysql $_writeConnection */
    protected $_writeConnection;

    /** @var Mage_Reports_Model_Resource_Product_Collection $_reportsProductCollection */
    protected $_reportsProductCollection;

    function __construct()
    {
        try {
            $this->_writeConnection = Mage::getSingleton('core/resource')
                ->getConnection('core_write');
            $this->_reportsProductCollection = Mage::getResourceModel('reports/product_collection');
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    /**
     * @param $name
     * @return array
     */
    protected function getCategoryId($name)
    {

        $category['bestsellers'] = 'Best of Backstage';
        $category['newestProducts'] = 'Neuheiten';
        $category['topRatedProducts'] = 'Am Besten Bewertet';

        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addFieldToFilter('name', $category[$name]);

        return $categories->getFirstItem()->getEntityId();
    }

    /**
     * @param $catId
     * @throws Mage_Core_Exception
     */
    protected function _deleteInCategory($catId)
    {
        $delQuery = "DELETE FROM catalog_category_product WHERE category_id = {$catId}";

        try {
            $this->_writeConnection->query($delQuery);
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    /**
     * get Bestsellers
     *
     * @return Mage_Sales_Model_Resource_Report_Bestsellers_Collection $collection
     */
    protected function bestsellers()
    {
        try {
            return Mage::getResourceModel('sales/report_bestsellers_collection');
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    /**
     * get newest products by creation date
     *
     * @return Mage_Reports_Model_Resource_Product_Collection $collection
     */
    protected function newestProducts()
    {
        try {
            $collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->addAttributeToSort('created_at', 'desc');
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);

            $select = $collection->getSelect();
            $select->limit(24);

            return $collection;
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    /**
     * get Top rated products
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     * @throws Mage_Core_Exception
     */
    protected function topRatedProducts()
    {
        try {
            $collection = Mage::getResourceModel('reports/product_collection')
                ->addAttributeToSelect('*')
                ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->joinField(
                    'rating_summary',
                    'review/review_aggregate',
                    'rating_summary',
                    'entity_pk_value=entity_id',
                    array('entity_type' => 1, 'store_id' => 3), 'left')
                ->setOrder('rating_summary', 'desc');

            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);

            $select = $collection->getSelect();
            $select->limit(24);

            return $collection;
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    /**
     * add products to specified category
     *
     * @param $collection
     * @param $categoryId
     * @return string
     * @throws Exception
     */
    protected function _addProductsToCategorySQL($collection, $categoryId)
    {
        if (!method_exists($collection, 'getItems')) {
            Mage::log("Method getItems doesn not existfor CategoryId: {$categoryId}");
            return false;
        }

        $x = 0;
        $str = '';

        $sql = "INSERT INTO catalog_category_product(category_id, product_id,  position) VALUES ";
        /** @var Mage_Catalog_Model_Product $product */
        foreach ($collection as $product) {
            $id = ($product->getId()) ? $product->getId() : $product->getProductId();

            if ($id == '') {
                Mage::log("id empty for CategoryId: {$categoryId}");
                continue;
            }

            $str .= "({$categoryId}, {$id}, {$x}),";
            ++$x;
        }

        $sql .= substr($str, 0, -1) . ';';

        return $sql;
    }

    /**
     * reindex again
     *
     * @throws Exception
     */
    protected function _reindexCategoryAndProduct()
    {
        $process = Mage::getModel('index/indexer')
            ->getProcessByCode('catalog_category_product');
        $process->reindexAll();
    }

    /**
     * @param $name
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function run($name)
    {
        $catId = $this->getCategoryId($name);
        $collection = $this->$name(); // call a function of this class using the action param from the form
        $this->_deleteInCategory($catId);
        $sql = $this->_addProductsToCategorySQL($collection, $catId);

        try {
            $this->_writeConnection->query($sql);
            $this->_reindexCategoryAndProduct();
        } catch (Exception $e) {
            Mage::throwException($e);
        }

        return true;
    }

}